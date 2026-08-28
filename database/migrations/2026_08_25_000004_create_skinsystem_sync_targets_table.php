<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skinsystem_sync_targets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->char('target_uuid', 36);
            $table->unsignedInteger('target_server_id');
            $table->string('status', 24)->default('possible_active')->index();
            $table->unsignedInteger('clear_revision')->nullable()->index();
            $table->unsignedInteger('queued_clear_command_id')->nullable()->unique();
            $table->boolean('clear_may_be_in_flight')->default(false);
            $table->timestamp('dispatched_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'target_uuid', 'target_server_id'],
                'skinsystem_sync_target_unique',
            );
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skinsystem_sync_targets');
    }
};
