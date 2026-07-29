<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment instructions shown to users when they request a topup: the admin
 * WhatsApp number plus bank details / steps as rich text.
 *
 * Exactly one row is served (is_active); older rows are kept as fallbacks.
 * `description` is sanitised on write — it is rendered as HTML in the user app.
 */
class DepositTopupInfo extends Model
{
    protected $table = 'deposit_topup_info';

    protected $fillable = [
        'wa_number',
        'description',
        'is_active',
        'seq',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'seq' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** CMS staff (users table) who last saved this row. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Digits-only form used to build a wa.me link. Accepts any input format —
     * "+62 812-8197-7739" and "08128197739" both become 628128197739.
     */
    public function waDigits(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->wa_number) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }

    public function waLink(): string
    {
        return 'https://wa.me/'.$this->waDigits();
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_active')->orderBy('seq')->orderByDesc('id');
    }
}
