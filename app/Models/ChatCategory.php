<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatCategory extends Model
{
    protected $table = 'chat_category';

    protected $fillable = [
        'name',
        'types',
        'seq',
        'is_active',
        'parent_id',
        'description',
    ];

    protected $casts = [
        'seq' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChatCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChatCategory::class, 'parent_id')->orderBy('seq')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChatCategoryItem::class, 'category_id');
    }

    /**
     * A leaf may hold content scope: it has no active child categories.
     */
    public function isLeaf(): bool
    {
        return ! $this->children()->where('is_active', true)->exists();
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('seq')->orderBy('id');
    }
}
