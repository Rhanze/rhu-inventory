<?php

namespace App\Http\Controllers;

use DomainException;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class Controller
{
    protected function operationErrorMessage(Throwable $error, string $fallback): string
    {
        if ($error instanceof DomainException || $error instanceof ValidationException) {
            return $error->getMessage();
        }

        report($error);

        return $fallback;
    }
}
