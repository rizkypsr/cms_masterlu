<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $table = 'book';

    public $timestamps = false;

    protected $fillable = [
        'book_category_id',
        'url',
        'url_pdf',
        'synopsis',
        'title',
        'seq',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'book_category_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(BookChapter::class, 'book_id')->whereNull('parent_id')->orderBy('seq');
    }
}
