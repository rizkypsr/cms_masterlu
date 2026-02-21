<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topic2Content extends Model
{
    protected $table = 'topics2_contents';

    public $timestamps = false;

    protected $fillable = [
        'topics2_chapters_id',
        'content',
        'page',
    ];

    public function topic2Chapter(): BelongsTo
    {
        return $this->belongsTo(Topic2Chapter::class, 'topics2_chapters_id');
    }
}
