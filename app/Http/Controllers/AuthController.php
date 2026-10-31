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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

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
        $attempts = Cache::get($throttleKey, 0);

        if ($attempts >= 5) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Too many login attempts. Please try again after 15 minutes.']);
        }

        // ========== 3. AUTHENTICATE USER ==========
        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Increment failed attempts and store with TTL (15 minutes)
            $attempts++;
            Cache::put($throttleKey, $attempts, 900); // 900 seconds = 15 minutes

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

        // ========== 5. LOGIN SUCCESS – CLEAR ATTEMPTS & SET SESSION ==========
        Cache::forget($throttleKey);

        $request->session()->regenerate();

        session([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'health_unit_id' => $user->health_unit_id,
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
