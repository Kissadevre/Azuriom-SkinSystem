<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skinsystem_saved_skins')) {
            if (! Schema::hasColumn('skinsystem_saved_skins', 'cape_id')) {
                Schema::table('skinsystem_saved_skins', function (Blueprint $table) {
                    $table->string('cape_id', 64)->nullable()->after('resolved_variant');
                });
            }

            if (! Schema::hasColumn('skinsystem_saved_skins', 'appearance_hash')) {
                Schema::table('skinsystem_saved_skins', function (Blueprint $table) {
                    $table->char('appearance_hash', 64)->nullable()->after('cape_id');
                });

                DB::table('skinsystem_saved_skins')
                    ->select(['id', 'sha256', 'variant', 'cape_id'])
                    ->orderBy('id')
                    ->eachById(function (object $skin) {
                        DB::table('skinsystem_saved_skins')
                            ->where('id', $skin->id)
                            ->update([
                                'appearance_hash' => hash(
                                    'sha256',
                                    $skin->sha256.'|'.$skin->variant.'|'.($skin->cape_id ?? 'none'),
                                ),
                            ]);
                    });

                Schema::table('skinsystem_saved_skins', function (Blueprint $table) {
                    $table->char('appearance_hash', 64)->nullable(false)->change();
                    $table->dropUnique('skinsystem_saved_skins_unique');
                    $table->unique(['user_id', 'appearance_hash'], 'skinsystem_saved_skins_unique');
                });
            }

            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('skinsystem_saved_skins');
    }
};
