<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keyword extends Model
{
    protected $table = 'keyword';

    public $timestamps = false;

    protected $fillable = [
        'keyword_category_id',
        'kata_kunci',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'keyword_category_id');
    }
}
