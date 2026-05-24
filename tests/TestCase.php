<?php

namespace LoggedCloud\PageStudio\Tests;

use LoggedCloud\PageStudio\PageStudioServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [PageStudioServiceProvider::class];
        if (class_exists(\Livewire\LivewireServiceProvider::class)) {
            $providers[] = \Livewire\LivewireServiceProvider::class;
        }
        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
