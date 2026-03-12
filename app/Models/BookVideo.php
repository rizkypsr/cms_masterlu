<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookVideo extends Model
{
    protected $table = 'book_video';

    public $timestamps = false;

    protected $fillable = [
        'book_chapters_id',
        'video_category_id',
        'seq',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BookChapter::class, 'book_chapters_id');
    }

    public function videoCategory(): BelongsTo
    {
        return $this->belongsTo(VideoCategory::class, 'video_category_id');
    }
}
