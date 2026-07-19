<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
        'plan_id',
        'plan_started_at',
        'plan_expires_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'social_media' => 'integer',
        'row_status' => 'integer',
        'plan_started_at' => 'datetime',
        'plan_expires_at' => 'datetime',
    ];

    // Social media constants
    const SOCIAL_MEDIA_NONE = 0;

    const SOCIAL_MEDIA_GOOGLE = 1;

    const SOCIAL_MEDIA_FACEBOOK = 2;

    public function playlists(): HasMany
    {
        return $this->hasMany(CommunityPlaylist::class, 'user_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'pengguna_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'pengguna_id');
    }

    public function chatMessages(): HasManyThrough
    {
        return $this->hasManyThrough(
            ChatMessage::class,
            ChatConversation::class,
            'pengguna_id',
            'conversation_id',
        );
    }

    /**
     * Whether the assigned plan is currently active (not expired).
     */
    public function hasActivePlan(): bool
    {
        if ($this->plan_id === null) {
            return false;
        }

        return $this->plan_expires_at === null || $this->plan_expires_at->isFuture();
    }

    /**
     * Get the user's display name (uses 'nama' field)
     */
    public function getNameAttribute(): string
    {
        return $this->nama ?? 'Unknown User';
    }
}
