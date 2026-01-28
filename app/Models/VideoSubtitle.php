<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoSubtitle extends Model
{
    protected $table = 'video_subtitle';

    public $timestamps = false;

    protected $fillable = [
        'video_id',
        'description',
        'timestamp',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
