<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topic3Video extends Model
{
    protected $table = 'topic3_video';

    public $timestamps = false;

    protected $fillable = [
        'topics3_chapters_id',
        'video_category_id',
        'seq',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Topic3Chapter::class, 'topics3_chapters_id');
    }

    public function videoCategory(): BelongsTo
    {
        return $this->belongsTo(VideoCategory::class, 'video_category_id');
    }
}
