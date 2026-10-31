<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class CredentialRules
{
    public static function password(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:4096', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()];
    }
}
