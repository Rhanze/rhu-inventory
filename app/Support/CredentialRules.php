<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class CredentialRules
{
    public static function password(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:4096', 'confirmed',
            function ($attribute, $value, $fail) {
                if (is_string($value) && config('hashing.driver', 'bcrypt') === 'bcrypt' && strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes when using bcrypt.');
                }
            }, Password::min(8)->mixedCase()->numbers()->symbols()];
    }
}
