<?php

namespace App\Support;

use Illuminate\Http\Request;

class ManagementDeviceAccess
{
    public const MOBILE_MESSAGE = 'Mobile access is available for midwives only. PHO admins, RHU admins, and staff must use a desktop or laptop.';

    public static function isMobile(Request $request): bool
    {
        if (trim((string) $request->header('Sec-CH-UA-Mobile')) === '?1') {
            return true;
        }

        // Include tablets, whose browsers do not always send the mobile client hint.
        return preg_match(
            '/Android|iPhone|iPad|iPod|Mobile|IEMobile|Windows Phone|BlackBerry|BB10|Opera Mini|Opera Mobi|Silk|Kindle/i',
            $request->userAgent() ?? ''
        ) === 1;
    }
}
