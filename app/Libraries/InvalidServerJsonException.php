<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Thrown when pasted Outline server JSON fails loose validation
 * (unparseable, or missing an https `apiUrl`). The message is user-facing —
 * the Add Server UI shows it inline.
 */
class InvalidServerJsonException extends RuntimeException
{
}
