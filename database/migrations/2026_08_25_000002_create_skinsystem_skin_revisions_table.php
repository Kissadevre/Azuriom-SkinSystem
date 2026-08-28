<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skinsystem_skin_revisions')) {
            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('skinsystem_skin_revisions');
    }
};
