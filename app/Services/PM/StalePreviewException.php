<?php

namespace App\Services\PM;

use RuntimeException;

/**
 * Exception yang dilempar ketika token preview sudah kedaluwarsa.
 *
 * Terjadi ketika state database berubah antara preview dan apply,
 * sehingga fingerprint yang dikirim klien tidak cocok dengan
 * fingerprint yang dihitung ulang saat apply.
 */
class StalePreviewException extends RuntimeException
{
    public function __construct(string $message = 'Preview sudah kedaluwarsa. Silakan jalankan preview ulang.', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
