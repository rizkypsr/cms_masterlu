<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicBookmark extends Model
{
    protected $fillable = [
        'title',
        'name',
        'pengguna_id',
        'seq',
        'is_active',
        'is_pinned',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
    ];

    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }
}
