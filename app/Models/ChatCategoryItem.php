<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCategoryItem extends Model
{
    protected $table = 'chat_category_item';

    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'domain',
        'level',
        'node_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'node_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChatCategory::class, 'category_id');
    }
}
