<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicContent extends Model
{
    protected $table = 'topics_content';

    public $timestamps = false;

    protected $fillable = [
        'topics_category_id',
        'type',
        'id_header',
        'seq',
    ];

    public function topicCategory()
    {
        return $this->belongsTo(TopicCategory::class, 'topics_category_id');
    }

    public function audio()
    {
        return $this->belongsTo(Audio::class, 'id_header')->where('type', 'audio');
    }

    public function video()
    {
        return $this->belongsTo(Video::class, 'id_header')->where('type', 'video');
    }
}
