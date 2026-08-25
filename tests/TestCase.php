<?php

namespace Azuriom\Plugin\SkinSystem\Tests;

use Azuriom\Http\Controllers\InstallController;
use Azuriom\Models\Role;
use Azuriom\Models\Server;
use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\RecordingGame;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\RecordingServerBridge;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected const PRIMARY_UUID = '12345678-1234-4234-8234-1234567890ab';

    protected const SECONDARY_UUID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

    public function createApplication(): Application
    {
        $configCache = __DIR__.'/cache/skinsystem-config.php';

        if (is_file($configCache)) {
            throw new RuntimeException('SkinSystem tests refuse to load a cached application configuration.');
        }

        $this->setEnvironmentVariables([
            'APP_ENV' => 'testing',
            'APP_KEY' => InstallController::TEMP_KEY,
            'APP_CONFIG_CACHE' => $configCache,
            'APP_ROUTES_CACHE' => __DIR__.'/cache/skinsystem-routes.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_PATH' => ':memory:',
            'DB_URL' => '(null)',
            'LOG_CHANNEL' => 'null',
        ]);

        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('app.key') !== InstallController::TEMP_KEY) {
            throw new RuntimeException('SkinSystem tests refuse to bootstrap an unsafe application environment.');
        }

        config([
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.previous_keys' => [],
            'app.url' => 'https://skins.example.com',
        ]);
        DB::purge('sqlite');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection('sqlite')->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('SkinSystem tests refuse to run outside SQLite memory.');
        }

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2019_08_13_000000_create_pages_table.php',
            '2019_08_15_000000_create_roles_table.php',
            '2019_08_22_000000_create_settings_table.php',
            '2019_08_30_000000_create_permissions_table.php',
            '2019_12_03_000000_create_servers_table.php',
            '2019_12_06_000000_create_server_commands_table.php',
            '2021_08_26_000000_create_redirects_table.php',
            '2022_02_26_000000_add_display_columns_to_servers_table.php',
        ] as $migration) {
            (require dirname(__DIR__, 3).'/database/migrations/'.$migration)->up();
        }

        foreach (glob(dirname(__DIR__).'/database/migrations/*.php') as $migrationPath) {
            (require $migrationPath)->up();
        }

        Role::create([
            'name' => 'Member',
            'color' => 'ffffff',
            'power' => 0,
            'is_admin' => false,
        ]);

        $this->app->instance('game', new RecordingGame);
        RecordingServerBridge::reset();
        Storage::fake('local');

        $this->app->make('translator')->addNamespace(
            'skinsystem',
            dirname(__DIR__).'/resources/lang',
        );

        Route::middleware([SubstituteBindings::class])
            ->prefix('api/skinsystem')
            ->name('skinsystem.api.')
            ->group(dirname(__DIR__).'/routes/api.php');
        Route::getRoutes()->refreshNameLookups();
    }

    protected function createUser(string $gameId = self::PRIMARY_UUID, string $name = 'TestPlayer'): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower($name).'@example.com',
            'password' => 'not-used-in-tests',
            'role_id' => 1,
            'money' => 0,
            'game_id' => $gameId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    protected function createServer(string $type = 'mc-azlink', string $name = 'Minecraft'): Server
    {
        return Server::create([
            'name' => $name,
            'address' => '127.0.0.1',
            'port' => 25565,
            'type' => $type,
            'token' => 'test-token',
            'data' => [],
        ]);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function setEnvironmentVariables(array $variables): void
    {
        foreach ($variables as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
