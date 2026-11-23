<?php

namespace Tests\Unit;

use App\Support\ManagementDeviceAccess;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ManagementDeviceAccessTest extends TestCase
{
    #[DataProvider('devices')]
    public function test_mobile_phones_and_tablets_are_identified(string $agent, ?string $hint, bool $expected): void
    {
        $request = Request::create('/');
        $request->headers->set('User-Agent', $agent);
        if ($hint !== null) {
            $request->headers->set('Sec-CH-UA-Mobile', $hint);
        }

        $this->assertSame($expected, ManagementDeviceAccess::isMobile($request));
    }

    public static function devices(): array
    {
        return [
            'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1', null, true],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1', null, true],
            'android phone' => ['Mozilla/5.0 (Linux; Android 14) Chrome/130.0.0.0 Mobile Safari/537.36', '?1', true],
            'android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X710) Chrome/130.0.0.0 Safari/537.36', '?0', true],
            'kindle' => ['Mozilla/5.0 (Linux; U; en-US) Silk/3.2 Safari/535.19', null, true],
            'mobile client hint' => ['Browser', '?1', true],
            'mobile agent with desktop hint' => ['iPhone Mobile', '?0', true],
            'windows desktop' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0.0.0 Safari/537.36', '?0', false],
            'mac desktop' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', null, false],
            'linux desktop' => ['Mozilla/5.0 (X11; Linux x86_64) Firefox/132.0', null, false],
            'no device information' => ['', null, false],
        ];
    }
}
