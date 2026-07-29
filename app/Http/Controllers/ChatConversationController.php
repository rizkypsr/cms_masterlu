<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Pengguna;
use App\Support\ChatCost;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only views over chat history and what it cost.
 *
 * Cost is always priced from `ai_rate` (via ChatCost) — the same table the chat
 * API bills from — and, where a charge was actually written, from the ledger
 * row itself. Amounts are rupiah, matching the deposit balance users see.
 */
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
            ->orderByDesc('id')
            ->paginate(20, ['id', 'pengguna_id', 'category_id', 'title', 'created_at', 'updated_at'])
            ->withQueryString();

        $costs = $this->costByConversation($conversations->pluck('id')->all());

        $conversations->through(fn (ChatConversation $c): array => [
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
            'cost_rp' => Money::toRupiah((int) round($costs[$c->id] ?? 0)),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ]);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'filters' => ['search' => $search],
            'unpricedModels' => $this->unpricedModels(),
        ]);
    }

    /**
     * Full message thread for one conversation.
     */
    public function show(ChatConversation $chatConversation): Response
    {
        $chatConversation->load(['pengguna:id,nama,username', 'category:id,name']);

        $messages = $chatConversation->messages()->orderBy('id')->get();
        $ledger = ChatCost::ledgerMrpByMessage($messages->pluck('id')->all());

        $rows = $messages->map(function (ChatMessage $m) use ($ledger): array {
            $charged = $ledger[$m->id] ?? null;
            $rated = $m->role === 'assistant'
                ? ChatCost::mrp($m->model, $m->prompt_tokens, $m->cached_input_tokens, $m->completion_tokens)
                : null;

            $mrp = $charged ?? $rated;

            return [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'flagged' => $m->flagged,
                'model' => $m->model,
                'prompt_tokens' => $m->prompt_tokens,
                'cached_input_tokens' => $m->cached_input_tokens,
                'completion_tokens' => $m->completion_tokens,
                'total_tokens' => $m->total_tokens,
                'cost_rp' => $mrp === null ? null : Money::toRupiah((int) round($mrp)),
                // Distinguishes "this is what we billed" from "this is what the
                // current rate says it would cost".
                'cost_source' => $charged !== null ? 'ledger' : ($rated !== null ? 'rate' : null),
                'created_at' => $m->created_at?->toIso8601String(),
            ];
        });

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
            'messages' => $rows,
            'total_tokens' => (int) $rows->sum('total_tokens'),
            'total_cost_rp' => (float) $rows->sum('cost_rp'),
            'unpricedModels' => $this->unpricedModels(),
        ]);
    }

    /**
     * Per-user token/cost totals, optionally scoped to a date range.
     */
    public function report(Request $request): Response
    {
        $from = $request->date('from');
        $to = $request->date('to');

        // One grouped pass over (user, model) — rates are then applied per model
        // in PHP, so any model the API starts using is priced automatically.
        $usage = ChatMessage::query()
            ->join('chat_conversation', 'chat_conversation.id', '=', 'chat_message.conversation_id')
            ->where('chat_message.role', 'assistant')
            ->whereNotNull('chat_message.total_tokens')
            ->when($from, fn ($q) => $q->where('chat_message.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('chat_message.created_at', '<', $to->copy()->addDay()))
            ->groupBy('chat_conversation.pengguna_id', 'chat_message.model')
            ->get([
                'chat_conversation.pengguna_id',
                'chat_message.model',
                DB::raw('SUM(chat_message.prompt_tokens) as prompt_tokens'),
                DB::raw('SUM(chat_message.cached_input_tokens) as cached_input_tokens'),
                DB::raw('SUM(chat_message.completion_tokens) as completion_tokens'),
                DB::raw('SUM(chat_message.total_tokens) as total_tokens'),
                DB::raw('COUNT(*) as messages'),
            ]);

        $perUser = $usage->groupBy('pengguna_id')->map(fn (Collection $rows): array => [
            'total_tokens' => (int) $rows->sum('total_tokens'),
            'messages' => (int) $rows->sum('messages'),
            'mrp' => $rows->sum(fn ($r): float => (float) ChatCost::mrp(
                $r->model,
                (int) $r->prompt_tokens,
                (int) $r->cached_input_tokens,
                (int) $r->completion_tokens,
            )),
        ]);

        $users = Pengguna::query()
            ->whereIn('id', $perUser->keys())
            ->withCount('chatConversations')
            ->get(['id', 'nama', 'username', 'deposit_balance_mrp'])
            ->map(fn (Pengguna $p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'username' => $p->username,
                'conversation_count' => $p->chat_conversations_count,
                'balance_rp' => Money::toRupiah($p->deposit_balance_mrp),
                'total_tokens' => $perUser[$p->id]['total_tokens'],
                'messages' => $perUser[$p->id]['messages'],
                'cost_rp' => Money::toRupiah((int) round($perUser[$p->id]['mrp'])),
            ])
            ->sortByDesc('cost_rp')
            ->values();

        return Inertia::render('Chat/Report', [
            'users' => $users,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'unpricedModels' => $this->unpricedModels(),
        ]);
    }

    /**
     * Cost in milli-rupiah per conversation id, ledger first then rate.
     *
     * @param  list<int>  $conversationIds
     * @return array<int, float>
     */
    private function costByConversation(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $messages = ChatMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('role', 'assistant')
            ->get(['id', 'conversation_id', 'model', 'prompt_tokens', 'cached_input_tokens', 'completion_tokens']);

        $ledger = ChatCost::ledgerMrpByMessage($messages->pluck('id')->all());

        return $messages
            ->groupBy('conversation_id')
            ->map(fn (Collection $rows): float => $rows->sum(
                fn (ChatMessage $m): float => $ledger[$m->id]
                    ?? (float) ChatCost::mrp($m->model, $m->prompt_tokens, $m->cached_input_tokens, $m->completion_tokens)
            ))
            ->all();
    }

    /**
     * Models that produced answers but have no active rate — those questions
     * are billed nothing at all, so this is worth surfacing rather than
     * silently showing Rp0.
     *
     * @return list<string>
     */
    private function unpricedModels(): array
    {
        $priced = ChatCost::rates()->keys();

        return ChatMessage::query()
            ->where('role', 'assistant')
            ->whereNotNull('model')
            ->whereNotIn('model', $priced)
            ->distinct()
            ->pluck('model')
            ->all();
    }
}
