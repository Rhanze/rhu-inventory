<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']['database.default'] === 'mysql' && ! preg_match('/^rhu_repair_test_[a-f0-9]{12}$/', $app['config']['database.connections.mysql.database'])) {
            throw new \RuntimeException('MySQL tests must use the disposable database runner: php tools/verify-database.php');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $compiled = storage_path('framework/testing-views');
        if (! is_dir($compiled)) {
            mkdir($compiled, 0777, true);
        }
        config(['view.compiled' => $compiled]);
    }
}
