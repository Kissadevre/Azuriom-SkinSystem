<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skinsystem_sync_targets', function (Blueprint $table) {
            $table->char('target_uuid', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('skinsystem_sync_targets', function (Blueprint $table) {
            $table->char('target_uuid', 36)->nullable(false)->change();
        });
    }
};
