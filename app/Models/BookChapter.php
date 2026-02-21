<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookChapter extends Model
{
    protected $table = 'book_chapters';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'book_id',
        'title',
        'description',
        'seq',
        'have_child',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BookChapter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BookChapter::class, 'parent_id')->orderBy('seq');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(BookContent::class, 'book_chapters_id')->orderBy('page');
    }
}
