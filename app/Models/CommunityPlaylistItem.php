<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPlaylistItem extends Model
{
    protected $fillable = [
        'community_playlist_id',
        'type',
        'data',
        'seq',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    // Type constants
    const TYPE_VIDEO = 1;

    const TYPE_AUDIO = 2;

    const TYPE_BOOK = 3;

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(CommunityPlaylist::class, 'community_playlist_id');
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_AUDIO => 'Audio',
            self::TYPE_VIDEO => 'Video',
            self::TYPE_BOOK => 'Book',
            default => 'Unknown',
        };
    }

    /**
     * Get the actual content based on type and data
     * Audio: {audioId: 2018, subtitleId: 1732, lang: "CN"}
     * Video: {videoId: null, video_category_id: 601, lang: "CN"}
     * Book: {book_id: 19, contentId: null, page: null}
     */
    public function getContentAttribute()
    {
        if (! $this->data) {
            return null;
        }

        switch ($this->type) {
            case self::TYPE_AUDIO:
                // If subtitleId exists, return audio subtitle, otherwise return audio
                if (isset($this->data['subtitleId']) && $this->data['subtitleId']) {
                    return AudioSubtitle::with('audio')->find($this->data['subtitleId']);
                } elseif (isset($this->data['audioId'])) {
                    return Audio::find($this->data['audioId']);
                }
                break;

            case self::TYPE_VIDEO:
                if (isset($this->data['video_category_id'])) {
                    return VideoCategory::find($this->data['video_category_id']);
                } elseif (isset($this->data['videoId'])) {
                    return Video::find($this->data['videoId']);
                }
                break;

            case self::TYPE_BOOK:
                if (isset($this->data['book_id'])) {
                    return Book::find($this->data['book_id']);
                }
                break;
        }

        return null;
    }

    public function getDisplayTitleAttribute(): string
    {
        $content = $this->content;

        if (! $content) {
            return 'Unknown Item';
        }

        switch ($this->type) {
            case self::TYPE_AUDIO:
                if ($content instanceof AudioSubtitle) {
                    $title = $content->title ?: ($content->audio ? $content->audio->title : 'Untitled');
                    // Format timestamp
                    if ($content->timestamp) {
                        $ms = is_numeric($content->timestamp) ? (int) $content->timestamp : 0;
                        $totalSeconds = floor($ms / 1000);
                        $hours = floor($totalSeconds / 3600);
                        $minutes = floor(($totalSeconds % 3600) / 60);
                        $seconds = $totalSeconds % 60;
                        $time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                        return $title.' - '.$time;
                    }

                    return $title;
                } elseif ($content instanceof Audio) {
                    return $content->title;
                }
                break;

            case self::TYPE_VIDEO:
                return $content->title ?? 'Untitled Video';

            case self::TYPE_BOOK:
                return $content->title ?? 'Untitled Book';
        }

        return 'Unknown Item';
    }
}
