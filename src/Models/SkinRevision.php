<?php

namespace Azuriom\Plugin\SkinSystem\Models;

use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $revision
 * @property string $file
 * @property string $sha256
 * @property string $resolved_variant
 * @property string|null $cape_id
 * @property string $delivery_strategy
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SkinRevision extends Model
{
    protected $table = 'skinsystem_skin_revisions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'revision',
        'file',
        'sha256',
        'resolved_variant',
        'cape_id',
        'delivery_strategy',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'revision' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
