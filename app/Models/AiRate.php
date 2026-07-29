<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI token pricing, stored exactly as the provider publishes it: USD per
 * 1.000.000 tokens, plus the exchange rate used at the time.
 *
 * Charge conversion is a single multiplication:
 *   milli-rupiah per 1.000 tokens = usd_per_1m * usd_to_idr
 *
 * The rate lives on the row rather than in a global setting so every past
 * charge stays reproducible with the exact numbers that produced it.
 *
 * Insert-only: never UPDATE an existing row's prices — old ledger rows point at
 * the rate that billed them. Exactly one active row per model.
 */
class AiRate extends Model
{
    protected $table = 'ai_rate';

    /** Only created_at exists; written explicitly so it stays UTC. */
    public $timestamps = false;

    protected $fillable = [
        'model',
        'input_usd_per_1m',
        'cached_input_usd_per_1m',
        'output_usd_per_1m',
        'embed_usd_per_1m',
        'usd_to_idr',
        'is_free_tier',
        'effective_from',
        'is_active',
        'note',
        'created_at',
    ];

    protected $casts = [
        // Rates only — actual charges are computed by the chat API in integer
        // milli-rupiah, so float here is display/preview precision, not money.
        'input_usd_per_1m' => 'float',
        'cached_input_usd_per_1m' => 'float',
        'output_usd_per_1m' => 'float',
        'embed_usd_per_1m' => 'float',
        'usd_to_idr' => 'integer',
        'is_free_tier' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Milli-rupiah charged per 1.000 tokens for a given USD/1M price.
     * Rounded up, matching how the chat API bills.
     */
    public function mrpPer1k(string $column): int
    {
        return (int) ceil($this->{$column} * $this->usd_to_idr);
    }

    /**
     * What a message with these token counts costs, in milli-rupiah.
     * Cached input tokens are billed at the cheaper cached rate and are a
     * subset of prompt_tokens, so they're deducted from the normal input side.
     */
    public function costMrp(?int $inputTokens, ?int $cachedInputTokens, ?int $outputTokens): float
    {
        $cached = $cachedInputTokens ?? 0;
        $uncached = max(0, ($inputTokens ?? 0) - $cached);

        return $uncached / 1000 * $this->mrpPer1k('input_usd_per_1m')
            + $cached / 1000 * $this->mrpPer1k('cached_input_usd_per_1m')
            + ($outputTokens ?? 0) / 1000 * $this->mrpPer1k('output_usd_per_1m');
    }

    /**
     * The rate that was active for a model at a given time — falls back to the
     * current active row when a message predates any recorded rate.
     */
    public static function activeFor(?string $model): ?self
    {
        if ($model === null || $model === '') {
            return null;
        }

        return static::where('model', $model)->where('is_active', true)->first();
    }
}
