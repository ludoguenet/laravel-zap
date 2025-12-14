<?php

namespace Zap\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use Orchestra\Testbench\TestCase as Orchestra;
use Zap\ZapServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithCachedConfig;
    use WithCachedRoutes;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ZapServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Load package configuration with test-friendly defaults
        $app['config']->set('zap', [
            'conflict_detection' => [
                'enabled' => true,
                'buffer_minutes' => 0,
            ],
            'validation' => [
                'require_future_dates' => false,
                'max_date_range' => 3650,
                'min_period_duration' => 1,
                'max_period_duration' => 1440,
                'max_periods_per_schedule' => 100,
                'allow_overlapping_periods' => true,
            ],
            'default_rules' => [
                'no_overlap' => [
                    'enabled' => true,
                    'applies_to' => ['appointment', 'blocked'],
                ],
                'working_hours' => [
                    'enabled' => false,
                    'start' => '09:00',
                    'end' => '17:00',
                ],
                'max_duration' => [
                    'enabled' => false,
                    'minutes' => 480,
                ],
                'no_weekends' => [
                    'enabled' => false,
                    'saturday' => true,
                    'sunday' => true,
                ],
            ],
            'cache' => [
                'enabled' => false,
            ],
            'availability_precedence' => [
                'single_date_over_range' => false, // Default: backward compatible behavior
            ],
        ]);

        // Database setup
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        include_once __DIR__.'/database/migrations/2025_11_23_create_zap_test_users_table.php';
        (new \CreateUsersTable)->up();
        include_once __DIR__.'/database/migrations/2025_11_23_create_zap_test_rooms_table.php';
        (new \CreateRoomsTable)->up();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
