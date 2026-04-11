<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unduh extends Model
{
    protected $table = 'unduh';

    public $timestamps = false;

    protected $fillable = [
        'unduh_category_id',
        'title',
        'is_pdf',
        'cover',
        'url',
        'link_url',
        'seq',
    ];

    protected $casts = [
        'is_pdf' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'unduh_category_id');
    }
}
