<?php

namespace Azuriom\Plugin\SkinSystem\Models;

use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $file
 * @property string $sha256
 * @property string $variant
 * @property string $resolved_variant
 * @property int $revision
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Azuriom\Models\User $user
 */
class Skin extends Model
{
    public const VARIANT_AUTO = 'auto';

    public const VARIANT_CLASSIC = 'classic';

    public const VARIANT_SLIM = 'slim';

    protected $table = 'skinsystem_skins';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'file',
        'sha256',
        'variant',
        'resolved_variant',
        'revision',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'revision' => 'integer',
    ];

    /**
     * Return the supported skin model choices.
     *
     * @return array<int, string>
     */
    public static function variants(): array
    {
        return [
            self::VARIANT_AUTO,
            self::VARIANT_CLASSIC,
            self::VARIANT_SLIM,
        ];
    }

    /**
     * Get the Azuriom account that owns the skin.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the immutable public PNG URL for this skin revision.
     */
    public function publicUrl(): string
    {
        return route('skinsystem.api.skins.show', [
            'user' => $this->user_id,
            'revision' => $this->revision,
            'hash' => $this->sha256,
        ]);
    }
}
