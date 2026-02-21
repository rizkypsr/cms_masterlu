<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $table = 'topics';

    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'title',
        'short_title',
        'seq',
        'icon',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function topicCategories()
    {
        return $this->hasMany(TopicCategory::class, 'topics_id');
    }
}
