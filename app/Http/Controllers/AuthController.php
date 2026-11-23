<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\Allocation;
use App\Models\Barangay;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Support\AdminMfa;
use App\Support\ManagementDeviceAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('user_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        // ========== 1. VALIDATION with security rules ==========
        $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
            ],
            'password' => [
                'required',
                'string',
                'max:4096',
            ],
        ]);

        // ========== 2. THROTTLE BRUTE FORCE ATTEMPTS ==========
        $throttleKey = 'login_attempts_'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Too many login attempts. Please try again after 15 minutes.']);
        }

        // ========== 3. AUTHENTICATE USER ==========
        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Shared cache increments preserve competing failures and a fixed retry window.
            RateLimiter::hit($throttleKey, 900);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'The provided credentials do not match our records.']);
        }

        // ========== 4. CHECK IF ACCOUNT IS ACTIVE ==========
        if (! $user->is_active) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'This account is currently deactivated. Please contact the Provincial Health Office administrator.']);
        }

        if (! in_array($user->role, ['admin', 'staff', 'midwife'], true)
            || ($user->role !== 'admin' && ! $user->health_unit_id)) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => 'This account needs a valid role and RHU assignment. Please contact your administrator.',
            ]);
        }

        // ========== 5. LOGIN SUCCESS – CLEAR ATTEMPTS & SET SESSION ==========
        if ($user->role !== 'midwife' && ManagementDeviceAccess::isMobile($request)) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => ManagementDeviceAccess::MOBILE_MESSAGE,
            ]);
        }

        RateLimiter::clear($throttleKey);

        if (AdminMfa::required($user)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($user->mfa_secret === null) {
                return redirect()->route('login')->withErrors([
                    'username' => 'Administrator verification must be enrolled. Contact the system operator.',
                ]);
            }

            $request->session()->put('pending_mfa', [
                'user_id' => $user->id,
                'auth_version' => (int) $user->auth_version,
                'expires_at' => now()->getTimestamp() + 300,
            ]);

            return redirect()->route('login.verification');
        }

        return $this->finishLogin($request, $user, false);
    }

    public function showVerification(Request $request)
    {
        if ($this->pendingMfa($request) === null) {
            return redirect()->route('login');
        }

        return view('auth.verification');
    }

    public function verify(Request $request)
    {
        $pending = $this->pendingMfa($request);
        if ($pending === null) {
            return redirect()->route('login');
        }

        $code = $request->input('mfa_code');
        if (! is_string($code) || ! preg_match('/\A[0-9]{6}\z/', $code)) {
            return back()->withErrors(['mfa_code' => 'Enter the six-digit code from your authenticator.']);
        }

        $accountKey = 'mfa-account:'.$pending['user_id'];
        $connectionKey = 'mfa-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($accountKey, 5) || RateLimiter::tooManyAttempts($connectionKey, 20)
            || RateLimiter::hit($accountKey, 300) > 5 || RateLimiter::hit($connectionKey, 300) > 20) {
            return back()->withErrors(['mfa_code' => 'Too many attempts. Wait five minutes and sign in again.']);
        }

        $user = DB::transaction(function () use ($pending, $code) {
            $user = User::whereKey($pending['user_id'])->lockForUpdate()->first();
            if (! $user || ! $user->is_active || ! AdminMfa::required($user) || $user->mfa_secret === null
                || (int) $user->auth_version !== $pending['auth_version']) {
                return null;
            }

            $step = AdminMfa::matchingStep($user->mfa_secret, $code, now()->getTimestamp(), $user->mfa_last_step);
            if ($step === null) {
                return null;
            }

            $user->mfa_last_step = $step;
            $user->save();

            return $user;
        }, 3);

        if ($user === null) {
            return back()->withErrors(['mfa_code' => 'The code could not be verified. Use a fresh code or sign in again.']);
        }

        RateLimiter::clear($accountKey);

        return $this->finishLogin($request, $user, true);
    }

    private function pendingMfa(Request $request): ?array
    {
        $pending = $request->session()->get('pending_mfa');
        if (! $pending || $pending['expires_at'] <= now()->getTimestamp()) {
            $request->session()->forget('pending_mfa');

            return null;
        }

        return $pending;
    }

    private function finishLogin(Request $request, User $user, bool $mfaVerified)
    {
        $request->session()->forget('pending_mfa');
        $request->session()->regenerate(true);

        session([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'health_unit_id' => $user->health_unit_id,
            'auth_version' => (int) $user->auth_version,
            'mfa_verified' => $mfaVerified,
        ]);

        ActivityLogger::log(
            'Authentication',
            'Login',
            $user->name.' logged into the system.',
            $user->health_unit_id,
            $user->id
        );

        return redirect()->route('dashboard');
    }

    public function dashboard()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        $user = User::with('healthUnit')->find(session('user_id'));

        if (! $user) {
            session()->flush();

            return redirect()->route('login');
        }

        $healthUnitId = session('health_unit_id');

        // ========== PROVINCIAL PHO ADMIN ==========
        if (is_null($healthUnitId)) {
            $stats = [
                'rhus_count' => HealthUnit::count(),
                'users_count' => User::count(),
                'medicines_count' => Medicine::count(),
                'inventory_count' => Inventory::count(),
                'total_available_units' => Inventory::sum('available_stock'),
                'barangays_count' => Barangay::count(),
                'low_stock_count' => Inventory::where('available_stock', '<=', 10)->count(),
                'near_expiry_count' => Inventory::whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '<=', Carbon::today()->addDays(30))
                    ->count(),
                'stock_logs_count' => StockLog::count(),
                'allocations_count' => Allocation::count(),
                'dispensing_count' => DispensingRecord::count(),
            ];

            $lowStockItems = Inventory::with(['medicine', 'healthUnit'])
                ->where('available_stock', '<=', 10)
                ->orderBy('available_stock', 'asc')
                ->take(5)->get();

            $nearExpiryItems = Inventory::with(['medicine', 'healthUnit'])
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', Carbon::today()->addDays(30))
                ->orderBy('expiration_date', 'asc')
                ->take(5)->get();

            $recentStockLogs = StockLog::with(['medicine', 'addedByUser', 'healthUnit'])
                ->orderByDesc('id')
                ->take(5)->get();

            $recentDispensing = DispensingRecord::with(['medicine', 'healthUnit'])
                ->orderByDesc('id')
                ->take(5)->get();

            return view('pho-dashboard.phoadmin', compact(
                'user', 'stats', 'lowStockItems', 'nearExpiryItems',
                'recentStockLogs', 'recentDispensing'
            ));
        }

        // ========== RHU‑LEVEL USER ==========
        $today = Carbon::today();
        $nearExpiryDate = Carbon::today()->addDays(30);

        $stats = [
            'users_count' => User::where('health_unit_id', $healthUnitId)->count(),
            'medicines_count' => Medicine::count(),
            'inventory_count' => Inventory::where('health_unit_id', $healthUnitId)->count(),
            'total_available_units' => Inventory::where('health_unit_id', $healthUnitId)->sum('available_stock'),
            'low_stock_count' => Inventory::where('health_unit_id', $healthUnitId)
                ->where('available_stock', '<=', 10)
                ->count(),
            'near_expiry_count' => Inventory::where('health_unit_id', $healthUnitId)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', $nearExpiryDate)
                ->count(),
            'barangays_count' => Barangay::where('health_unit_id', $healthUnitId)->count(),
            'allocations_count' => Allocation::where('health_unit_id', $healthUnitId)->count(),
            'dispensing_count' => DispensingRecord::where('health_unit_id', $healthUnitId)->count(),
            'stock_logs_count' => StockLog::where('health_unit_id', $healthUnitId)->count(),
        ];

        $lowStockItems = Inventory::with('medicine')
            ->where('health_unit_id', $healthUnitId)
            ->where('available_stock', '<=', 10)
            ->orderBy('available_stock', 'asc')
            ->take(5)->get();

        $nearExpiryItems = Inventory::with('medicine')
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $nearExpiryDate)
            ->orderBy('expiration_date', 'asc')
            ->take(5)->get();

        $recentStockLogs = StockLog::with(['medicine', 'addedByUser'])
            ->where('health_unit_id', $healthUnitId)
            ->orderByDesc('id')
            ->take(5)->get();

        $recentDispensing = DispensingRecord::with(['medicine', 'dispensedByUser'])
            ->where('health_unit_id', $healthUnitId)
            ->orderByDesc('id')
            ->take(5)->get();

        // Return the appropriate dashboard for the role
        if ($user->role === 'admin') {
            return view('admin.rhu-dashboard', compact(
                'user', 'stats', 'lowStockItems', 'nearExpiryItems',
                'recentStockLogs', 'recentDispensing'
            ));
        }

        if ($user->role === 'staff') {
            return view('staff.dashboard', compact(
                'user', 'stats', 'lowStockItems', 'nearExpiryItems',
                'recentStockLogs', 'recentDispensing'
            ));
        }

        session()->flush();

        return redirect()->route('login')->with('error', 'Invalid user role.');
    }

    public function logout(Request $request)
    {
        ActivityLogger::log(
            'Authentication',
            'Logout',
            session('user_name').' logged out of the system.'
        );

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
