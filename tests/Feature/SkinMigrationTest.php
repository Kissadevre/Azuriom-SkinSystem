<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkinMigrationTest extends TestCase
{
    private const TABLES = [
        'skinsystem_skins',
        'skinsystem_skin_revisions',
        'skinsystem_sync_states',
        'skinsystem_sync_targets',
        'skinsystem_saved_skins',
        'skinsystem_mineskin_generations',
    ];

    public function test_consolidated_migration_creates_and_drops_the_complete_schema(): void
    {
        $migrationPath = dirname(__DIR__, 2)
            .'/database/migrations/2026_08_25_000000_create_skinsystem_skins_table.php';

        $this->assertCount(1, glob(dirname($migrationPath).'/*.php'));

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $migration = require $migrationPath;
        $migration->down();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }

        $migration->up();

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasColumns('skinsystem_skins', [
            'user_id',
            'file',
            'sha256',
            'variant',
            'resolved_variant',
            'cape_id',
            'delivery_strategy',
            'revision',
        ]));
        $this->assertTrue(Schema::hasColumns('skinsystem_sync_states', [
            'target_uuid',
            'target_server_id',
            'queued_command_id',
            'dispatched_at',
            'error',
        ]));
        $this->assertTrue(Schema::hasColumns('skinsystem_sync_targets', [
            'clear_revision',
            'queued_clear_command_id',
            'clear_may_be_in_flight',
        ]));
        $this->assertTrue(Schema::hasColumns('skinsystem_mineskin_generations', [
            'user_id',
            'skin_revision',
            'appearance_hash',
            'status',
            'job_id',
            'result_uuid',
            'result_url',
            'next_poll_at',
        ]));
    }

    public function test_consolidated_migration_compiles_with_the_mariadb_schema_grammar(): void
    {
        $migration = require dirname(__DIR__, 2)
            .'/database/migrations/2026_08_25_000000_create_skinsystem_skins_table.php';
        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.database' => 'skinsystem_test',
        ]);
        DB::purge('mariadb');

        try {
            $queries = DB::connection('mariadb')->pretend(fn () => $migration->up());
        } finally {
            config(['database.default' => 'sqlite']);
            DB::purge('mariadb');
        }

        $sql = implode("\n", array_column($queries, 'query'));

        foreach (self::TABLES as $table) {
            $this->assertStringContainsString("create table `{$table}`", strtolower($sql));
        }

        $this->assertStringContainsString('engine = innodb', strtolower($sql));
        $this->assertStringContainsString('foreign key (`user_id`) references `users` (`id`) on delete cascade', strtolower($sql));
    }
}
