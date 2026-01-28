<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoCategory extends Model
{
    protected $table = 'video_category';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'category_id',
        'sub_category_id',
        'title',
        'seq',
        'is_parent',
        'translate_id',
        'translate_ch',
    ];

    protected $casts = [
        'is_parent' => 'integer',
        'seq' => 'integer',
        'translate_id' => 'integer',
        'translate_ch' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(VideoCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(VideoCategory::class, 'parent_id')->orderBy('seq');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'video_category_id');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('seq');
    }
}
