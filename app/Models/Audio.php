<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audio extends Model
{
    public $timestamps = false;

    protected $table = 'audio';

    protected $fillable = [
        'audio_sub_group_id',
        'title',
        'url',
        'duration',
        'seq',
        'translate_id',
        'translate_ch',
    ];

    public function subGroup(): BelongsTo
    {
        return $this->belongsTo(AudioSubGroup::class, 'audio_sub_group_id');
    }

    public function subtitles(): HasMany
    {
        return $this->hasMany(AudioSubtitle::class, 'audio_id');
    }
}
