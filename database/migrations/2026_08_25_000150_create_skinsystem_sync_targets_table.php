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
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        $now = now();
        $hasLastSetTarget = Schema::hasColumn('skinsystem_sync_states', 'last_set_uuid')
            && Schema::hasColumn('skinsystem_sync_states', 'last_set_server_id');

        DB::table('skinsystem_sync_states')
            ->select(array_filter([
                'id',
                'user_id',
                'action',
                'skin_revision',
                'status',
                'target_uuid',
                'target_server_id',
                'queued_command_id',
                'dispatched_at',
                'error',
                $hasLastSetTarget ? 'last_set_uuid' : null,
                $hasLastSetTarget ? 'last_set_server_id' : null,
            ]))
            ->orderBy('id')
            ->chunkById(100, function ($states) use ($hasLastSetTarget, $now) {
                foreach ($states as $state) {
                    $targets = [];

                    if ($hasLastSetTarget) {
                        $lastSet = $this->validTarget(
                            $state->last_set_uuid,
                            $state->last_set_server_id,
                        );

                        if ($lastSet !== null) {
                            $targets[$lastSet['uuid'].'|'.$lastSet['server_id']] = $lastSet;
                        }
                    }

                    $current = $this->validTarget($state->target_uuid, $state->target_server_id);
                    $queueOwner = null;

                    if ($current !== null) {
                        $queueOwner = $current['uuid'].'|'.$current['server_id'];
                        $targets[$queueOwner] = $current;
                    }

                    $queueOwner ??= array_key_first($targets);

                    foreach ($targets as $targetKey => $target) {
                        $isClear = $state->action === 'clear';
                        $isCurrentClearTarget = $isClear && $targetKey === $queueOwner;
                        $attributes = [
                            'status' => $isCurrentClearTarget
                                ? $this->clearStatus($state->status)
                                : ($isClear ? 'clear_pending' : 'possible_active'),
                            'clear_revision' => $isClear ? $state->skin_revision : null,
                            'queued_clear_command_id' => $isCurrentClearTarget
                                ? $state->queued_command_id
                                : null,
                            'clear_may_be_in_flight' => $isCurrentClearTarget
                                && ($state->queued_command_id !== null || $state->status === 'uncertain'),
                            'dispatched_at' => $isCurrentClearTarget ? $state->dispatched_at : null,
                            'error' => $isCurrentClearTarget ? $state->error : null,
                            'updated_at' => $now,
                        ];

                        DB::table('skinsystem_sync_targets')->updateOrInsert(
                            [
                                'user_id' => $state->user_id,
                                'target_uuid' => $target['uuid'],
                                'target_server_id' => $target['server_id'],
                            ],
                            ['created_at' => $now, ...$attributes],
                        );
                    }

                    if ($state->action === 'clear'
                        && $state->queued_command_id !== null
                        && $queueOwner !== null) {
                        DB::table('skinsystem_sync_states')
                            ->where('id', $state->id)
                            ->update(['queued_command_id' => null]);
                    }
                }
            });
    }

    /**
     * @return array{uuid: string, server_id: int}|null
     */
    private function validTarget(mixed $uuid, mixed $serverId): ?array
    {
        if (! is_string($uuid)
            || preg_match('/^(?:[a-fA-F0-9]{32}|[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})$/D', $uuid) !== 1
            || (! is_int($serverId) && (! is_string($serverId) || preg_match('/^[1-9][0-9]*$/D', $serverId) !== 1))) {
            return null;
        }

        $serverId = (int) $serverId;

        if ($serverId < 1 || $serverId > 2147483647) {
            return null;
        }

        $hex = strtolower(str_replace('-', '', $uuid));

        return [
            'uuid' => substr($hex, 0, 8).'-'
                .substr($hex, 8, 4).'-'
                .substr($hex, 12, 4).'-'
                .substr($hex, 16, 4).'-'
                .substr($hex, 20, 12),
            'server_id' => $serverId,
        ];
    }

    private function clearStatus(mixed $status): string
    {
        return match ($status) {
            'submitted' => 'clear_submitted',
            'failed' => 'clear_failed',
            'uncertain' => 'clear_uncertain',
            default => 'clear_pending',
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skinsystem_sync_targets');
    }
};
