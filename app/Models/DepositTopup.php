<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositTopup extends Model
{
    protected $table = 'deposit_topup';

    protected $fillable = [
        'pengguna_id',
        'amount_mrp',
        'credited_mrp',
        'status',
        'payment_note',
        'rejected_reason',
        'approved_by',
        'paid_at',
    ];

    protected $casts = [
        'amount_mrp' => 'integer',
        'credited_mrp' => 'integer',
        'approved_by' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * CMS staff (users table) who verified this request — not a pengguna.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
