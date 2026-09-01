<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skinsystem_sync_states', function (Blueprint $table) {
            $table->string('target_type', 16)->default('uuid');
            $table->string('target_value', 64)->nullable();
        });

        Schema::table('skinsystem_sync_targets', function (Blueprint $table) {
            $table->dropUnique('skinsystem_sync_target_unique');
            $table->string('target_type', 16)->default('uuid');
            $table->string('target_value', 64)->nullable();
        });

        DB::table('skinsystem_sync_states')
            ->whereNull('target_value')
            ->update(['target_value' => DB::raw('target_uuid')]);
        DB::table('skinsystem_sync_targets')
            ->whereNull('target_value')
            ->update(['target_value' => DB::raw('target_uuid')]);

        Schema::table('skinsystem_sync_targets', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'target_type', 'target_value', 'target_server_id'],
                'skinsystem_sync_target_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('skinsystem_sync_targets', function (Blueprint $table) {
            $table->dropUnique('skinsystem_sync_target_identity_unique');
            $table->dropColumn(['target_type', 'target_value']);
            $table->unique(
                ['user_id', 'target_uuid', 'target_server_id'],
                'skinsystem_sync_target_unique',
            );
        });

        Schema::table('skinsystem_sync_states', function (Blueprint $table) {
            $table->dropColumn(['target_type', 'target_value']);
        });
    }
};
