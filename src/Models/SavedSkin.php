<?php

namespace Azuriom\Plugin\SkinSystem\Models;

use Azuriom\Models\User;
use Illuminate\Database\Eloquent\Model;

class SavedSkin extends Model
{
    protected $table = 'skinsystem_saved_skins';

    protected $fillable = [
        'user_id',
        'name',
        'file',
        'sha256',
        'variant',
        'resolved_variant',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
