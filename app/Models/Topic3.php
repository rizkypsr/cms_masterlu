<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic3 extends Model
{
    protected $table = 'topics3';

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

    public function topics3Chapters(): HasMany
    {
        return $this->hasMany(Topic3Chapter::class, 'topics3_id')->whereNull('parent_id')->orderBy('seq');
    }
}
