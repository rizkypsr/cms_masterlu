<?php

namespace App\Models;

use App\Support\ChatCost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'chat_message';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'sources',
        'flagged',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
    ];

    protected $casts = [
        'flagged' => 'boolean',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function costUsd(): float
    {
        return ChatCost::usd($this->model, $this->prompt_tokens, $this->completion_tokens);
    }
}
