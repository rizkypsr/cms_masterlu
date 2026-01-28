<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    protected $table = 'video';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'video_category_id',
        'location_id',
        'video_sub_group_id',
        'title',
        'synopsis',
        'url',
        'url_audio',
        'seq',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(VideoCategory::class, 'video_category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Video::class, 'parent_id')->orderBy('seq')->orderBy('id');
    }

    public function subGroup(): BelongsTo
    {
        return $this->belongsTo(VideoSubGroup::class, 'video_sub_group_id');
    }

    public function subGroups(): HasMany
    {
        return $this->hasMany(VideoSubGroup::class, 'video_id')->orderBy('seq')->orderBy('id');
    }

    public function subtitles(): HasMany
    {
        return $this->hasMany(VideoSubtitle::class, 'video_id')->orderBy('timestamp');
    }
}
