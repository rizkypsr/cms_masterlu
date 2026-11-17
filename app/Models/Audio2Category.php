<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audio2Category extends Model
{
    public $timestamps = false;

    protected $table = 'audio2_category';

    protected $fillable = [
        'title',
        'seq',
        'source_category_id',
    ];

    public function audios(): HasMany
    {
        return $this->hasMany(Audio2::class, 'audio2_category_id');
    }
}
