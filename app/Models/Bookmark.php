<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    protected $table = 'bookmark';
    
    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'parent_id',
        'title',
        'data',
        'type',
    ];

    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    public function publicBookmarks()
    {
        return $this->hasMany(PublicBookmark::class, 'bookmark_id');
    }
}
