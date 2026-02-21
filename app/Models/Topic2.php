<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic2 extends Model
{
    protected $table = 'topics2';

    public $timestamps = false;

    protected $fillable = [
        'book_category_id',
        'synopsis',
        'title',
        'seq',
        'date',
    ];

    public function bookCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'book_category_id');
    }

    public function topics2Chapters(): HasMany
    {
        return $this->hasMany(Topic2Chapter::class, 'topics2_id')->whereNull('parent_id')->orderBy('seq');
    }
}
