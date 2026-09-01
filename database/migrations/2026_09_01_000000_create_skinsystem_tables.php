<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skinsystem_skins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('file');
            $table->char('sha256', 64)->index();
            $table->string('variant', 16)->default('auto');
            $table->string('resolved_variant', 16)->default('classic');
            $table->string('cape_id', 64)->nullable();
            $table->string('delivery_strategy', 16)->default('direct');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('skinsystem_skin_revisions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('revision');
            $table->string('file');
            $table->char('sha256', 64);
            $table->string('resolved_variant', 16);
            $table->string('cape_id', 64)->nullable();
            $table->string('delivery_strategy', 16)->default('direct');
            $table->timestamps();

            $table->unique(['user_id', 'revision']);
            $table->index(['user_id', 'sha256']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('skinsystem_sync_states', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('action', 16);
            $table->unsignedInteger('skin_revision')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->char('target_uuid', 36)->nullable();
            $table->string('target_type', 16)->default('uuid');
            $table->string('target_value', 64)->nullable();
            $table->unsignedInteger('target_server_id')->nullable()->index();
            // This column owns only the current SET row. Per-target CLEAR
            // ownership lives in skinsystem_sync_targets.
            $table->unsignedInteger('queued_command_id')->nullable()->unique();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('skinsystem_sync_targets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->char('target_uuid', 36);
            $table->string('target_type', 16)->default('uuid');
            $table->string('target_value', 64)->nullable();
            $table->unsignedInteger('target_server_id');
            $table->string('status', 24)->default('possible_active')->index();
            $table->unsignedInteger('clear_revision')->nullable()->index();
            $table->unsignedInteger('queued_clear_command_id')->nullable()->unique();
            $table->boolean('clear_may_be_in_flight')->default(false);
            $table->timestamp('dispatched_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'target_type', 'target_value', 'target_server_id'],
                'skinsystem_sync_target_identity_unique',
            );
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('skinsystem_saved_skins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name', 16);
            $table->string('file');
            $table->char('sha256', 64)->index();
            $table->string('variant', 16)->default('auto');
            $table->string('resolved_variant', 16)->default('classic');
            $table->string('cape_id', 64)->nullable();
            $table->char('appearance_hash', 64);
            $table->timestamps();

            $table->unique(['user_id', 'appearance_hash'], 'skinsystem_saved_skins_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('skinsystem_mineskin_generations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('skin_revision');
            $table->char('appearance_hash', 64)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('job_id', 64)->nullable()->unique();
            $table->string('result_uuid', 64)->nullable();
            $table->string('result_url', 512)->nullable();
            $table->string('error', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_poll_at')->nullable()->index();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'skin_revision'], 'skinsystem_mineskin_revision_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skinsystem_mineskin_generations');
        Schema::dropIfExists('skinsystem_saved_skins');
        Schema::dropIfExists('skinsystem_sync_targets');
        Schema::dropIfExists('skinsystem_sync_states');
        Schema::dropIfExists('skinsystem_skin_revisions');
        Schema::dropIfExists('skinsystem_skins');
    }
};
