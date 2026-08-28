<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skinsystem_sync_states')) {
            return;
        }

        Schema::create('skinsystem_sync_states', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('action', 16);
            $table->unsignedInteger('skin_revision')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->char('target_uuid', 36)->nullable();
            $table->unsignedInteger('target_server_id')->nullable()->index();
            // This column owns only the current SET row. Per-target CLEAR
            // ownership lives in skinsystem_sync_targets.
            $table->unsignedInteger('queued_command_id')->nullable()->unique();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skinsystem_sync_states');
    }
};
