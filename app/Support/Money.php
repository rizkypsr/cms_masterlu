<?php

namespace App\Support;

/**
 * Milli-rupiah <-> rupiah conversion. All deposit money columns are BIGINT
 * milli-rupiah (Rp1 = 1000) so per-question costs like Rp28,45 stay exact —
 * see DEPOSIT_CMS_ADMIN rule 2. Never store rupiah directly.
 */
class Money
{
    public const PER_RUPIAH = 1000;

    public static function toRupiah(?int $mrp): float
    {
        return ($mrp ?? 0) / self::PER_RUPIAH;
    }

    public static function toMrp(int|float $rupiah): int
    {
        return (int) round($rupiah * self::PER_RUPIAH);
    }
}
