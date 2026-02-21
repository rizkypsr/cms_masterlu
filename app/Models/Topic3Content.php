<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topic3Content extends Model
{
    protected $table = 'topics3_contents';

    public $timestamps = false;

    protected $fillable = [
        'topics3_chapters_id',
        'category_id',
        'content',
        'page',
    ];

    public function topic3Chapter(): BelongsTo
    {
        return $this->belongsTo(Topic3Chapter::class, 'topics3_chapters_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Topic3ContentCategory::class, 'category_id');
    }
}
