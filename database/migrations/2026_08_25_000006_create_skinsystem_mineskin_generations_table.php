<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skinsystem_mineskin_generations')) {
            return;
        }

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
    }
};
