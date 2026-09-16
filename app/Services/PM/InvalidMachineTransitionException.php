<?php

namespace App\Services\PM;

use RuntimeException;

/**
 * Menandai pelanggaran kontrak lifecycle Machine yang wajib gagal tertutup.
 */
class InvalidMachineTransitionException extends RuntimeException {}
