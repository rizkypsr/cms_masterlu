<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengguna extends Model
{
    protected $table = 'pengguna';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'nama',
        'email',
        'password',
        'username',
        'social_media',
        'row_status',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'social_media' => 'integer',
        'row_status' => 'integer',
    ];

    // Social media constants
    const SOCIAL_MEDIA_NONE = 0;

    const SOCIAL_MEDIA_GOOGLE = 1;

    const SOCIAL_MEDIA_FACEBOOK = 2;

    public function playlists(): HasMany
    {
        return $this->hasMany(CommunityPlaylist::class, 'user_id');
    }

    /**
     * Get the user's display name (uses 'nama' field)
     */
    public function getNameAttribute(): string
    {
        return $this->nama ?? 'Unknown User';
    }
}
