<?php

namespace App\Support;

use App\Models\AiRate;
use App\Models\DepositLedger;
use Illuminate\Support\Collection;

/**
 * Single source of truth for what a chat message cost.
 *
 * Price comes from the `ai_rate` table — the same rows the chat API bills
 * from — so the number an admin sees matches what the user was charged. There
 * is deliberately no second price list in config; two disconnected sources
 * means no figure anyone can rely on when a user complains.
 *
 * Preference order per message:
 *   1. The ledger row that actually charged it (authoritative, incl. shadow mode)
 *   2. The active rate for that message's model (what it would be charged)
 */
class ChatCost
{
    /** @var Collection<string, AiRate>|null */
    private static ?Collection $rates = null;

    /**
     * Active rate per model, loaded once per request.
     *
     * @return Collection<string, AiRate>
     */
    public static function rates(): Collection
    {
        return self::$rates ??= AiRate::where('is_active', true)->get()->keyBy('model');
    }

    public static function forget(): void
    {
        self::$rates = null;
    }

    /**
     * Milli-rupiah a message costs, priced from its own model's rate.
     * Returns null when no rate exists for that model — the chat API bills
     * nothing in that case, so showing a guessed figure would be misleading.
     */
    public static function mrp(?string $model, ?int $inputTokens, ?int $cachedInputTokens, ?int $outputTokens): ?float
    {
        $rate = self::rates()->get((string) $model);

        return $rate?->costMrp($inputTokens, $cachedInputTokens, $outputTokens);
    }

    /**
     * What the ledger records for these chat messages, keyed by message id.
     * In shadow mode (DEPOSIT_ENFORCE=false) delta_mrp is 0 and the would-be
     * cost is parked in note as `shadow:<mrp>`, so read that instead.
     *
     * @param  list<int>  $messageIds
     * @return array<int, float>
     */
    public static function ledgerMrpByMessage(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        return DepositLedger::where('ref_type', 'chat_message')
            ->whereIn('ref_id', $messageIds)
            ->get(['ref_id', 'delta_mrp', 'note'])
            ->mapWithKeys(function (DepositLedger $row): array {
                $charged = (float) abs($row->delta_mrp);

                if ($charged === 0.0 && $row->note !== null && str_starts_with($row->note, 'shadow:')) {
                    $charged = (float) filter_var($row->note, FILTER_SANITIZE_NUMBER_INT);
                }

                return [(int) $row->ref_id => $charged];
            })
            ->all();
    }
}
