<?php

namespace Azuriom\Plugin\SkinSystem\Models;

use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

class MineSkinGeneration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'skinsystem_mineskin_generations';

    protected $fillable = [
        'user_id',
        'skin_revision',
        'appearance_hash',
        'status',
        'job_id',
        'result_uuid',
        'result_url',
        'error',
        'attempts',
        'next_poll_at',
        'last_polled_at',
        'completed_at',
    ];

    protected $casts = [
        'skin_revision' => 'integer',
        'attempts' => 'integer',
        'next_poll_at' => 'datetime',
        'last_polled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->result_url !== null;
    }
}
