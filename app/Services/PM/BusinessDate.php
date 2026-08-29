<?php

namespace App\Services\PM;

use Carbon\Carbon;

/**
 * Satu-satunya sumber tanggal "hari ini" versi bisnis (Asia/Jakarta).
 *
 * Berbeda dari timezone global aplikasi; dipakai oleh validasi, perencanaan,
 * dan preview agar semuanya konsisten pada tanggal bisnis yang sama tanpa
 * memutar timezone global. Bekerja dengan Carbon::setTestNow() pada test.
 */
class BusinessDate
{
    public static function today(): Carbon
    {
        return Carbon::now('Asia/Jakarta')->startOfDay();
    }
}
