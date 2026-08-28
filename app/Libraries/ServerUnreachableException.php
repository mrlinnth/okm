<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Thrown when a server passes JSON validation but its `apiUrl` cannot be
 * reached during the Add Server light reachability check. Kept distinct
 * from InvalidServerJsonException so the UI can tell the two failures apart.
 */
class ServerUnreachableException extends RuntimeException
{
}
