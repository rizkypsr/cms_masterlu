<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookContent extends Model
{
    protected $table = 'book_contents';

    public $timestamps = false;

    protected $fillable = [
        'book_chapters_id',
        'content',
        'page',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BookChapter::class, 'book_chapters_id');
    }

    public function audios(): HasMany
    {
        return $this->hasMany(BookAudio::class, 'book_content_id')->orderBy('seq');
    }
}
