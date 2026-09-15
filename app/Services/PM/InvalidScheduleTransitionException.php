<?php

namespace App\Services\PM;

use RuntimeException;

/**
 * Exception transisi lifecycle Schedule yang ditolak.
 *
 * Dilempar fail-closed oleh ScheduleLifecycleService ketika transisi tidak
 * terdaftar pada matriks ADR-010 (misal ENDED -> ACTIVE) atau state/argumen
 * tidak dikenal. Tidak mengubah state tersimpan.
 */
class InvalidScheduleTransitionException extends RuntimeException
{
    public function __construct(string $message = 'Transisi lifecycle schedule tidak diizinkan.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
