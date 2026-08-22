<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Thrown when a request to an Outline server is rejected or fails.
 * Carries the full underlying error text — callers (delete-all, migrate)
 * need it verbatim for per-item failure reporting.
 */
class OutlineRequestException extends RuntimeException
{
}
