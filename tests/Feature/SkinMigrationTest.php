<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkinMigrationTest extends TestCase
{
    private const BASELINE_MIGRATION = '2026_09_01_000000_create_skinsystem_tables.php';

    private const TABLES = [
        'skinsystem_skins',
        'skinsystem_skin_revisions',
        'skinsystem_sync_states',
        'skinsystem_sync_targets',
        'skinsystem_saved_skins',
        'skinsystem_mineskin_generations',
    ];

    public function test_baseline_migration_creates_and_drops_the_complete_schema(): void
    {
        $migration = $this->baselineMigration();

        $this->assertSame(
            [self::BASELINE_MIGRATION],
            array_map('basename', glob($this->migrationDirectory().'/*.php')),
        );

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $migration->down();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' was not dropped.');
        }

        $migration->up();

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertExpectedColumns();
    }

    public function test_baseline_migration_compiles_with_the_mariadb_schema_grammar(): void
    {
        $migration = $this->baselineMigration();
        $migration->down();

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

            $migration->up();
        }

        $sql = implode("\n", array_column($queries, 'query'));

        foreach (self::TABLES as $table) {
            $this->assertStringContainsString("create table `{$table}`", strtolower($sql));
        }

        $this->assertStringContainsString('engine = innodb', strtolower($sql));
        $this->assertStringContainsString('foreign key (`user_id`) references `users` (`id`) on delete cascade', strtolower($sql));
        $this->assertStringContainsString(
            'alter table `skinsystem_sync_targets` add unique `skinsystem_sync_target_identity_unique`',
            strtolower($sql),
        );
    }

    private function baselineMigration(): object
    {
        return require $this->migrationDirectory().'/'.self::BASELINE_MIGRATION;
    }

    private function migrationDirectory(): string
    {
        return dirname(__DIR__, 2).'/database/migrations';
    }

    private function assertExpectedColumns(): void
    {
        $expectedColumns = [
            'skinsystem_skins' => [
                'id', 'user_id', 'file', 'sha256', 'variant', 'resolved_variant',
                'cape_id', 'delivery_strategy', 'revision', 'created_at', 'updated_at',
            ],
            'skinsystem_skin_revisions' => [
                'id', 'user_id', 'revision', 'file', 'sha256', 'resolved_variant',
                'cape_id', 'delivery_strategy', 'created_at', 'updated_at',
            ],
            'skinsystem_sync_states' => [
                'id', 'user_id', 'action', 'skin_revision', 'status', 'target_uuid',
                'target_type', 'target_value', 'target_server_id', 'queued_command_id',
                'dispatched_at', 'error', 'created_at', 'updated_at',
            ],
            'skinsystem_sync_targets' => [
                'id', 'user_id', 'target_uuid', 'target_type', 'target_value',
                'target_server_id', 'status', 'clear_revision', 'queued_clear_command_id',
                'clear_may_be_in_flight', 'dispatched_at', 'error', 'created_at', 'updated_at',
            ],
            'skinsystem_saved_skins' => [
                'id', 'user_id', 'name', 'file', 'sha256', 'variant', 'resolved_variant',
                'cape_id', 'appearance_hash', 'created_at', 'updated_at',
            ],
            'skinsystem_mineskin_generations' => [
                'id', 'user_id', 'skin_revision', 'appearance_hash', 'status', 'job_id',
                'result_uuid', 'result_url', 'error', 'attempts', 'next_poll_at',
                'last_polled_at', 'completed_at', 'created_at', 'updated_at',
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                $table.' does not contain the complete release schema.',
            );
        }
    }
}
