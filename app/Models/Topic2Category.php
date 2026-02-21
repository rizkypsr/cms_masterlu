<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic2Category extends Model
{
    protected $table = 'topics2_category';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'topics2_id',
        'title',
        'seq',
        'have_child',
    ];

    public function topic2(): BelongsTo
    {
        return $this->belongsTo(Topic2::class, 'topics2_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic2Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Topic2Category::class, 'parent_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Topic2Content::class, 'topics2_category_id');
    }
}
