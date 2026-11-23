<?php

namespace App\Support;

use App\Models\User;
use SensitiveParameter;

final class AdminMfa
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function required(User $user): bool
    {
        return ($user->role === 'admin' || $user->is_emergency)
            && config('auth.require_admin_mfa');
    }

    public static function generateSecret(): string
    {
        $key = '';
        foreach (unpack('C*', random_bytes(20)) as $byte) {
            $key .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($bits) => self::ALPHABET[bindec($bits)], str_split($key, 5)));
    }

    public static function matchingStep(#[SensitiveParameter] string $secret, #[SensitiveParameter] string $code, int $time, ?int $lastStep = null): ?int
    {
        if (! preg_match('/\A[A-Z2-7]{32}\z/', $secret) || ! preg_match('/\A[0-9]{6}\z/', $code)) {
            return null;
        }

        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $character)), 5, '0', STR_PAD_LEFT);
        }
        $key = implode('', array_map(fn ($byte) => chr(bindec($byte)), str_split($bits, 8)));
        $current = intdiv($time, 30);

        foreach ([$current, $current - 1] as $step) {
            if ($step < 0 || ($lastStep !== null && $step <= $lastStep)) {
                continue;
            }

            $counter = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
            $digest = hash_hmac('sha1', $counter, $key, true);
            $offset = ord($digest[19]) & 15;
            $value = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;
            if (hash_equals(str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT), $code)) {
                return $step;
            }
        }

        return null;
    }
}
