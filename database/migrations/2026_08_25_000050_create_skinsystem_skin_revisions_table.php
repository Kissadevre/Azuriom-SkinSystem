<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skinsystem_skin_revisions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('revision');
            $table->string('file');
            $table->char('sha256', 64);
            $table->string('resolved_variant', 16);
            $table->timestamps();

            $table->unique(['user_id', 'revision']);
            $table->index(['user_id', 'sha256']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        $now = now();

        DB::table('skinsystem_skins')
            ->select(['id', 'user_id', 'revision', 'file', 'sha256', 'resolved_variant'])
            ->orderBy('id')
            ->chunkById(100, function ($skins) use ($now) {
                DB::table('skinsystem_skin_revisions')->insert(
                    $skins->map(fn ($skin) => [
                        'user_id' => $skin->user_id,
                        'revision' => $skin->revision,
                        'file' => $skin->file,
                        'sha256' => $skin->sha256,
                        'resolved_variant' => $skin->resolved_variant,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skinsystem_skin_revisions');
    }
};
