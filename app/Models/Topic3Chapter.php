<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic3Chapter extends Model
{
    protected $table = 'topics3_chapters';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'topics3_id',
        'title',
        'description',
        'seq',
        'have_child',
    ];

    public function topic3(): BelongsTo
    {
        return $this->belongsTo(Topic3::class, 'topics3_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic3Chapter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Topic3Chapter::class, 'parent_id')->orderBy('seq');
    }

    public function topics3Contents(): HasMany
    {
        return $this->hasMany(Topic3Content::class, 'topics3_chapters_id')->orderBy('page');
    }
}
