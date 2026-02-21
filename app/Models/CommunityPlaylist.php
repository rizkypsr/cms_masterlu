<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPlaylist extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommunityPlaylistItem::class, 'community_playlist_id')->orderBy('seq');
    }

    public function getItemCountAttribute(): int
    {
        return $this->items()->count();
    }
}
