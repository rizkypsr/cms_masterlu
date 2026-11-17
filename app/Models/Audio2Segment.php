<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audio2Segment extends Model
{
    public $timestamps = false;

    protected $table = 'audio2_segment';

    protected $fillable = [
        'audio2_id',
        'caller',
        'code',
        'timestamp',
        'title',
        'content',
        'seq',
    ];

    protected function casts(): array
    {
        return [
            'caller' => 'integer',
            'timestamp' => 'integer',
        ];
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Audio2::class, 'audio2_id');
    }
}
