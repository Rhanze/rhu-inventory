<?php

namespace App\Console\Commands;

use App\Helpers\ActivityLogger;
use App\Models\User;
use App\Support\AdminMfa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrollAdminMfa extends Command
{
    protected $signature = 'admin:mfa-enroll {username} {--replace : Replace a previously enrolled authenticator}';

    protected $description = 'Enroll an administrator authenticator from a trusted interactive console';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Authenticator enrollment requires a trusted interactive console.');

            return self::FAILURE;
        }

        $account = User::where('username', $this->argument('username'))->first();
        if (! $account || ! $account->is_active || ($account->role !== 'admin' && ! $account->is_emergency)) {
            $this->error('Choose an active privileged account.');

            return self::FAILURE;
        }
        if ($account->mfa_secret !== null && (! $this->option('replace') || ! $this->confirm('Replace this authenticator and sign out existing sessions?'))) {
            return self::FAILURE;
        }

        $key = AdminMfa::generateSecret();
        $this->line('Add this key to your authenticator app as a time-based account (SHA-1, six digits, 30 seconds).');
        $this->line('Enrollment key (keep private): '.$key);
        $this->line('Type the six-digit code from the app here. The digits are hidden as you type.');

        $step = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $code = $this->secret('Six-digit authenticator code');
            if ($code === null) {
                break;
            }

            $code = trim($code);
            if (! preg_match('/\A[0-9]{6}\z/', $code)) {
                $this->warn('Enter only the six digits shown in your app.');
                continue;
            }

            $step = AdminMfa::matchingStep($key, $code, now()->getTimestamp());
            if ($step !== null) {
                break;
            }

            $this->warn('The code did not match. Use a fresh code from the authenticator entry created with the key above.');
        }

        if ($step === null) {
            $this->error('Verification failed. Run enrollment again to generate a new key.');

            return self::FAILURE;
        }

        $saved = DB::transaction(function () use ($account, $key, $step) {
            $user = User::whereKey($account->id)->lockForUpdate()->first();
            if (! $user || ! $user->is_active || ($user->role !== 'admin' && ! $user->is_emergency)
                || $user->auth_version !== $account->auth_version) {
                return false;
            }

            $user->mfa_secret = $key;
            $user->mfa_last_step = $step;
            $user->auth_version = (int) $user->auth_version + 1;
            $user->save();
            ActivityLogger::log('Authentication', 'Enroll MFA', 'Authenticator enrolled for account #'.$user->id, $user->health_unit_id);

            return true;
        }, 3);

        if (! $saved) {
            $this->error('The account changed during enrollment. Start enrollment again.');

            return self::FAILURE;
        }

        $this->info('Authenticator enrolled. Sign in with the next code from the authenticator.');

        return self::SUCCESS;
    }
}
