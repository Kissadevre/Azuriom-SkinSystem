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

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        $now = now();
        $configuredServerId = DB::table('settings')
            ->where('name', 'skinsystem.server_id')
            ->value('value');
        $configuredServerId = (is_int($configuredServerId)
            || (is_string($configuredServerId) && preg_match('/^[1-9][0-9]*$/D', $configuredServerId) === 1))
            && (int) $configuredServerId > 0
            && (int) $configuredServerId <= 2147483647
            ? (int) $configuredServerId
            : null;

        DB::table('skinsystem_skins')
            ->select(['id', 'user_id', 'revision'])
            ->orderBy('id')
            ->chunkById(100, function ($skins) use ($now, $configuredServerId) {
                $gameIds = DB::table('users')
                    ->whereIn('id', $skins->pluck('user_id'))
                    ->pluck('game_id', 'id');

                DB::table('skinsystem_sync_states')->insert(
                    $skins->map(function ($skin) use ($now, $configuredServerId, $gameIds) {
                        $gameId = $gameIds->get($skin->user_id);
                        $targetUuid = null;

                        if (is_string($gameId)
                            && preg_match('/^(?:[a-fA-F0-9]{32}|[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})$/D', $gameId) === 1) {
                            $hex = strtolower(str_replace('-', '', $gameId));
                            $targetUuid = substr($hex, 0, 8).'-'
                                .substr($hex, 8, 4).'-'
                                .substr($hex, 12, 4).'-'
                                .substr($hex, 16, 4).'-'
                                .substr($hex, 20, 12);
                        }

                        return [
                            'user_id' => $skin->user_id,
                            'action' => 'set',
                            'skin_revision' => $skin->revision,
                            'status' => 'pending',
                            'target_uuid' => $targetUuid,
                            'target_server_id' => $configuredServerId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all()
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skinsystem_sync_states');
    }
};
