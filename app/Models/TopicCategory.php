<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicCategory extends Model
{
    protected $table = 'topics_category';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'topics_id',
        'title',
        'seq',
        'have_child',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topics_id');
    }

    public function parent()
    {
        return $this->belongsTo(TopicCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TopicCategory::class, 'parent_id');
    }

    public function contents()
    {
        return $this->hasMany(TopicContent::class, 'topics_category_id');
    }
}
