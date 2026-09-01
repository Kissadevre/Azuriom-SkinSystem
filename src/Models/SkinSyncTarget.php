<?php

namespace Azuriom\Plugin\SkinSystem\Models;

use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A UUID/server pair that may currently hold a SkinSystem-managed selection.
 *
 * @property int $id
 * @property int $user_id
 * @property string $target_uuid
 * @property string $target_type
 * @property string $target_value
 * @property int $target_server_id
 * @property string $status
 * @property int|null $clear_revision
 * @property int|null $queued_clear_command_id
 * @property bool $clear_may_be_in_flight
 * @property \Carbon\Carbon|null $dispatched_at
 * @property string|null $error
 */
class SkinSyncTarget extends Model
{
    public const STATUS_POSSIBLE_ACTIVE = 'possible_active';

    public const STATUS_CLEAR_PENDING = 'clear_pending';

    public const STATUS_CLEAR_SUBMITTED = 'clear_submitted';

    public const STATUS_CLEAR_FAILED = 'clear_failed';

    public const STATUS_CLEAR_UNCERTAIN = 'clear_uncertain';

    protected $table = 'skinsystem_sync_targets';

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'target_uuid',
        'target_type',
        'target_value',
        'target_server_id',
        'status',
        'clear_revision',
        'queued_clear_command_id',
        'clear_may_be_in_flight',
        'dispatched_at',
        'error',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'target_server_id' => 'integer',
        'clear_revision' => 'integer',
        'queued_clear_command_id' => 'integer',
        'clear_may_be_in_flight' => 'boolean',
        'dispatched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
