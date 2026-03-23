<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicBookmark extends Model
{
    protected $fillable = [
        'bookmark_id',
        'seq',
        'is_active',
        'is_pinned',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
    ];

    public function bookmark()
    {
        return $this->belongsTo(Bookmark::class, 'bookmark_id');
    }
}
