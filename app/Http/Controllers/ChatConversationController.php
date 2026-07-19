<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Pengguna;
use App\Support\ChatCost;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatConversationController extends Controller
{
    /**
     * List every conversation across all users, with token/cost totals.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $conversations = ChatConversation::query()
            ->with(['pengguna:id,nama,username', 'category:id,name'])
            ->withCount('messages')
            ->withSum(['messages as total_tokens_sum' => fn ($q) => $q->where('role', 'assistant')], 'total_tokens')
            ->withSum($this->modelSumConstraints('messages', 'prompt_tokens'), 'prompt_tokens')
            ->withSum($this->modelSumConstraints('messages', 'completion_tokens'), 'completion_tokens')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('pengguna', function ($q) use ($search): void {
                            $q->where('nama', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20, ['id', 'pengguna_id', 'category_id', 'title', 'created_at', 'updated_at'])
            ->withQueryString()
            ->through(fn (ChatConversation $c): array => [
                'id' => $c->id,
                'title' => $c->title,
                'pengguna' => $c->pengguna ? [
                    'id' => $c->pengguna->id,
                    'nama' => $c->pengguna->nama,
                    'username' => $c->pengguna->username,
                ] : null,
                'category' => $c->category?->name,
                'message_count' => $c->messages_count,
                'total_tokens' => (int) $c->total_tokens_sum,
                'cost_usd' => $this->costFromAggregates($c),
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Full message thread for one conversation.
     */
    public function show(ChatConversation $chatConversation): Response
    {
        $chatConversation->load(['pengguna:id,nama,username', 'category:id,name']);

        $messages = $chatConversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $m): array => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'flagged' => $m->flagged,
                'model' => $m->model,
                'prompt_tokens' => $m->prompt_tokens,
                'completion_tokens' => $m->completion_tokens,
                'total_tokens' => $m->total_tokens,
                'cost_usd' => $m->role === 'assistant' ? $m->costUsd() : null,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Chat/Show', [
            'conversation' => [
                'id' => $chatConversation->id,
                'title' => $chatConversation->title,
                'pengguna' => $chatConversation->pengguna ? [
                    'id' => $chatConversation->pengguna->id,
                    'nama' => $chatConversation->pengguna->nama,
                    'username' => $chatConversation->pengguna->username,
                ] : null,
                'category' => $chatConversation->category?->name,
                'created_at' => $chatConversation->created_at?->toIso8601String(),
            ],
            'messages' => $messages,
            'total_tokens' => (int) $messages->sum('total_tokens'),
            'total_cost_usd' => $messages->sum('cost_usd'),
        ]);
    }

    /**
     * Per-user token/cost totals across all their conversations, optionally
     * scoped to a date range.
     */
    public function report(Request $request): Response
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $dateFilter = function ($q) use ($from, $to): void {
            if ($from) {
                $q->where('chat_message.created_at', '>=', $from);
            }
            if ($to) {
                $q->where('chat_message.created_at', '<', $to->copy()->addDay());
            }
        };

        $users = Pengguna::query()
            ->withCount('chatConversations')
            ->withSum(['chatMessages as total_tokens_sum' => function ($q) use ($dateFilter): void {
                $q->where('role', 'assistant');
                $dateFilter($q);
            }], 'total_tokens')
            ->withSum($this->modelSumConstraints('chatMessages', 'prompt_tokens', $dateFilter), 'prompt_tokens')
            ->withSum($this->modelSumConstraints('chatMessages', 'completion_tokens', $dateFilter), 'completion_tokens')
            ->having('total_tokens_sum', '>', 0)
            ->orderByDesc('total_tokens_sum')
            ->paginate(20, ['id', 'nama', 'username'])
            ->withQueryString()
            ->through(fn (Pengguna $p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'username' => $p->username,
                'conversation_count' => $p->chat_conversations_count,
                'total_tokens' => (int) $p->total_tokens_sum,
                'cost_usd' => $this->costFromAggregates($p),
            ]);

        return Inertia::render('Chat/Report', [
            'users' => $users,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    /**
     * One withSum constraint per known model, aliased "{column}_{model-slug}",
     * so cost can be computed per model without raw SQL SUM/GROUP BY.
     *
     * @return array<string, Closure>
     */
    private function modelSumConstraints(string $relation, string $column, ?Closure $extra = null): array
    {
        $constraints = [];

        foreach (ChatCost::models() as $model) {
            $slug = Str::slug($model, '_');
            $constraints["{$relation} as {$column}_{$slug}"] = function ($q) use ($model, $extra): void {
                $q->where('role', 'assistant')->where('model', $model);
                if ($extra !== null) {
                    $extra($q);
                }
            };
        }

        return $constraints;
    }

    /**
     * Sum per-model cost from the aggregate columns modelSumConstraints() added.
     */
    private function costFromAggregates(Model $row): float
    {
        $total = 0.0;

        foreach (ChatCost::models() as $model) {
            $slug = Str::slug($model, '_');
            $prompt = (int) ($row->{"prompt_tokens_{$slug}"} ?? 0);
            $completion = (int) ($row->{"completion_tokens_{$slug}"} ?? 0);
            $total += ChatCost::usd($model, $prompt, $completion);
        }

        return $total;
    }
}
