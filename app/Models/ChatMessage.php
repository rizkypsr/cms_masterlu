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
        'cached_input_tokens',
        'completion_tokens',
        'total_tokens',
    ];

    protected $casts = [
        'flagged' => 'boolean',
        'prompt_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /**
     * Milli-rupiah this message costs at its model's active rate.
     * Null when no rate covers the model — the chat API bills nothing then.
     */
    public function costMrp(): ?float
    {
        return ChatCost::mrp($this->model, $this->prompt_tokens, $this->cached_input_tokens, $this->completion_tokens);
    }
}
