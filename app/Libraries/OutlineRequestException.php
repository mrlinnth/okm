<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Thrown when a request to an Outline server is rejected or fails.
 * Carries the full underlying error text — callers (delete-all, migrate)
 * need it verbatim for per-item failure reporting.
 *
 * The $notFound flag marks the specific case where a key targeted for
 * deletion no longer exists on the server. Callers such as the expiry job
 * treat that as success (the desired end state — no live key — already
 * holds) rather than a failure to retry.
 */
class OutlineRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $notFound = false,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }
}
