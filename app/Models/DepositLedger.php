<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only balance ledger — the authoritative record.
 * pengguna.deposit_balance_mrp is only a cache of SUM(delta_mrp) here.
 * Never UPDATE or DELETE these rows.
 */
class DepositLedger extends Model
{
    protected $table = 'deposit_ledger';

    /** Only created_at exists; written explicitly so it stays UTC. */
    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'delta_mrp',
        'type',
        'balance_after_mrp',
        'ref_type',
        'ref_id',
        'rate_id',
        'model',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'note',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'delta_mrp' => 'integer',
        'balance_after_mrp' => 'integer',
        'ref_id' => 'integer',
        'rate_id' => 'integer',
        'input_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * CMS staff (users table) who wrote this row. NULL on `spend` rows —
     * those are written automatically by the chat API.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
