<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookAudio extends Model
{
    protected $table = 'book_audio';

    public $timestamps = false;

    protected $fillable = [
        'book_content_id',
        'title',
        'url',
        'seq',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(BookContent::class, 'book_content_id');
    }
}
