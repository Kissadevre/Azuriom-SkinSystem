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
