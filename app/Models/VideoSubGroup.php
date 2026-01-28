<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoSubGroup extends Model
{
    protected $table = 'video_sub_group';

    public $timestamps = false;

    protected $fillable = [
        'video_id',
        'name',
        'seq',
    ];

    protected $casts = [
        'seq' => 'integer',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'video_sub_group_id')->orderBy('seq')->orderBy('id');
    }
}
