<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic3ContentCategory extends Model
{
    protected $table = 'topics3_content_category';

    protected $fillable = [
        'name',
        'description',
        'seq',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(Topic3Content::class, 'category_id');
    }
}
