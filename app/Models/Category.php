<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $table = 'category';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'title',
        'parent_id',
        'seq',
        'type',
        'languange',
        'date',
        'image',
    ];

    protected $casts = [
        'seq' => 'integer',
        'date' => 'date',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('seq');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('seq');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
