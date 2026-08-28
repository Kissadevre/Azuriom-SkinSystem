<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skinsystem_skins')) {
            if (! Schema::hasColumn('skinsystem_skins', 'cape_id')) {
                Schema::table('skinsystem_skins', function (Blueprint $table) {
                    $table->string('cape_id', 64)->nullable()->after('resolved_variant');
                });
            }

            if (! Schema::hasColumn('skinsystem_skins', 'delivery_strategy')) {
                Schema::table('skinsystem_skins', function (Blueprint $table) {
                    $table->string('delivery_strategy', 16)->default('direct')->after('cape_id');
                });
            }

            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('skinsystem_skins');
    }
};
