<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkinMigrationTest extends TestCase
{
    private const TABLE_MIGRATIONS = [
        'skinsystem_skins' => '2026_08_25_000001_create_skinsystem_skins_table.php',
        'skinsystem_skin_revisions' => '2026_08_25_000002_create_skinsystem_skin_revisions_table.php',
        'skinsystem_sync_states' => '2026_08_25_000003_create_skinsystem_sync_states_table.php',
        'skinsystem_sync_targets' => '2026_08_25_000004_create_skinsystem_sync_targets_table.php',
        'skinsystem_saved_skins' => '2026_08_25_000005_create_skinsystem_saved_skins_table.php',
        'skinsystem_mineskin_generations' => '2026_08_25_000006_create_skinsystem_mineskin_generations_table.php',
    ];

    public function test_split_migrations_create_and_drop_their_own_tables(): void
    {
        $migrations = $this->tableMigrations();

        $this->assertCount(7, glob($this->migrationDirectory().'/*.php'));

        foreach (self::TABLE_MIGRATIONS as $table => $migrationName) {
            $this->assertArrayHasKey($migrationName, $migrations);
            $this->assertTrue(Schema::hasTable($table));
        }

        foreach (array_reverse($migrations, true) as $migration) {
            $migration->down();
        }

        foreach (self::TABLE_MIGRATIONS as $table => $migrationName) {
            $this->assertFalse(Schema::hasTable($table), $migrationName.' did not drop its table.');
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        foreach (array_keys(self::TABLE_MIGRATIONS) as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertExpectedColumns();
    }

    public function test_split_migrations_adopt_tables_from_the_legacy_migration(): void
    {
        foreach ($this->tableMigrations() as $migration) {
            $migration->up();
        }

        foreach (array_keys(self::TABLE_MIGRATIONS) as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    public function test_split_migrations_upgrade_the_pre_mineskin_schema_without_losing_saved_skins(): void
    {
        $migrations = $this->tableMigrations();

        $migrations[self::TABLE_MIGRATIONS['skinsystem_saved_skins']]->down();
        $migrations[self::TABLE_MIGRATIONS['skinsystem_skin_revisions']]->down();
        $migrations[self::TABLE_MIGRATIONS['skinsystem_skins']]->down();

        Schema::create('skinsystem_skins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('file');
            $table->char('sha256', 64)->index();
            $table->string('variant', 16)->default('auto');
            $table->string('resolved_variant', 16)->default('classic');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('skinsystem_skin_revisions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('revision');
            $table->string('file');
            $table->char('sha256', 64);
            $table->string('resolved_variant', 16);
            $table->timestamps();
        });
        Schema::create('skinsystem_saved_skins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name', 16);
            $table->string('file');
            $table->char('sha256', 64)->index();
            $table->string('variant', 16)->default('auto');
            $table->string('resolved_variant', 16)->default('classic');
            $table->timestamps();
            $table->unique(['user_id', 'sha256', 'variant'], 'skinsystem_saved_skins_unique');
        });

        $sha256 = str_repeat('a', 64);
        DB::table('skinsystem_saved_skins')->insert([
            'user_id' => 42,
            'name' => 'Legacy',
            'file' => 'skinsystem/library/legacy.png',
            'sha256' => $sha256,
            'variant' => 'classic',
            'resolved_variant' => 'classic',
        ]);

        $migrations[self::TABLE_MIGRATIONS['skinsystem_skins']]->up();
        $migrations[self::TABLE_MIGRATIONS['skinsystem_skin_revisions']]->up();
        $migrations[self::TABLE_MIGRATIONS['skinsystem_saved_skins']]->up();

        $this->assertTrue(Schema::hasColumns('skinsystem_skins', ['cape_id', 'delivery_strategy']));
        $this->assertTrue(Schema::hasColumns('skinsystem_skin_revisions', ['cape_id', 'delivery_strategy']));
        $this->assertTrue(Schema::hasColumns('skinsystem_saved_skins', ['cape_id', 'appearance_hash']));
        $this->assertSame(
            hash('sha256', $sha256.'|classic|none'),
            DB::table('skinsystem_saved_skins')->value('appearance_hash'),
        );
    }

    public function test_split_migrations_compile_with_the_mariadb_schema_grammar(): void
    {
        foreach (array_reverse($this->tableMigrations(), true) as $migration) {
            $migration->down();
        }

        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.database' => 'skinsystem_test',
        ]);
        DB::purge('mariadb');

        try {
            $queries = DB::connection('mariadb')->pretend(function () {
                foreach ($this->tableMigrations() as $migration) {
                    $migration->up();
                }
            });
        } finally {
            config(['database.default' => 'sqlite']);
            DB::purge('mariadb');

            foreach ($this->tableMigrations() as $migration) {
                $migration->up();
            }
        }

        $sql = implode("\n", array_column($queries, 'query'));

        foreach (array_keys(self::TABLE_MIGRATIONS) as $table) {
            $this->assertStringContainsString("create table `{$table}`", strtolower($sql));
        }

        $this->assertStringContainsString('engine = innodb', strtolower($sql));
        $this->assertStringContainsString('foreign key (`user_id`) references `users` (`id`) on delete cascade', strtolower($sql));
    }

    /**
     * @return array<string, object>
     */
    private function tableMigrations(): array
    {
        $migrations = [];

        foreach (self::TABLE_MIGRATIONS as $migrationName) {
            $migrations[$migrationName] = require $this->migrationDirectory().'/'.$migrationName;
        }

        return $migrations;
    }

    private function migrationDirectory(): string
    {
        return dirname(__DIR__, 2).'/database/migrations';
    }

    private function assertExpectedColumns(): void
    {
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
}
