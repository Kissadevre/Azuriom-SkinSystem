<?php

namespace Azuriom\Plugin\SkinSystem\Models;

use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property int|null $skin_revision
 * @property string $status
 * @property string|null $target_uuid
 * @property int|null $target_server_id
 * @property int|null $queued_command_id Exact AzLink SET row owned by this operation
 * @property \Carbon\Carbon|null $dispatched_at
 * @property string|null $error
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SkinSyncState extends Model
{
    public const ACTION_SET = 'set';

    public const ACTION_CLEAR = 'clear';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNCERTAIN = 'uncertain';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    protected $table = 'skinsystem_sync_states';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'skin_revision',
        'status',
        'target_uuid',
        'target_server_id',
        'queued_command_id',
        'dispatched_at',
        'error',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'skin_revision' => 'integer',
        'target_server_id' => 'integer',
        'queued_command_id' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
