<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioSubGroup extends Model
{
    public $timestamps = false;

    protected $table = 'audio_sub_group';

    protected $fillable = [
        'audio_category_id',
        'name',
        'seq',
        'have_child',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'audio_category_id');
    }

    public function audios(): HasMany
    {
        return $this->hasMany(Audio::class, 'audio_sub_group_id');
    }
}
