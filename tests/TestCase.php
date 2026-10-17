<?php

namespace JeffersonGoncalves\SavedFilter\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\PestPluginDatabaseMigrations\LoadsPackageMigrations;
use JeffersonGoncalves\SavedFilter\SavedFilterServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use LoadsPackageMigrations;

    protected function getPackageProviders($app): array
    {
        return [
            SavedFilterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testing_connection());
    }

    /**
     * In-memory SQLite by default; CI (tests.yml) sets SAVED_FILTER_TEST_DB_* to run the suite on MySQL and
     * PostgreSQL too. Not the plain DB_* names: Testbench sets DB_CONNECTION=testing itself.
     *
     * @return array<string, mixed>
     */
    protected function testing_connection(): array
    {
        $driver = env('SAVED_FILTER_TEST_DB_DRIVER', 'sqlite');

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        }

        return [
            'driver' => $driver,
            'host' => env('SAVED_FILTER_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('SAVED_FILTER_TEST_DB_PORT'),
            'database' => env('SAVED_FILTER_TEST_DB_DATABASE', 'testing'),
            'username' => env('SAVED_FILTER_TEST_DB_USERNAME', 'root'),
            'password' => env('SAVED_FILTER_TEST_DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations(SavedFilterServiceProvider::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // MySQL and PostgreSQL outlive the test, unlike in-memory SQLite.
        $this->beforeApplicationDestroyed(fn () => Schema::dropIfExists('users'));
    }
}
