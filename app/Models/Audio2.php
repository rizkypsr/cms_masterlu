<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audio2 extends Model
{
    public $timestamps = false;

    protected $table = 'audio2';

    protected $fillable = [
        'audio2_category_id',
        'title',
        'url',
        'duration',
        'year',
        'month',
        'aired_on',
        'seq',
        'source_audio_id',
    ];

    protected function casts(): array
    {
        return [
            'aired_on' => 'date:Y-m-d',
            'year' => 'integer',
            'month' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Audio2Category::class, 'audio2_category_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Audio2Segment::class, 'audio2_id')->orderBy('seq')->orderBy('id');
    }
}
