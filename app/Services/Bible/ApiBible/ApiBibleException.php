<?php

namespace App\Services\Bible\ApiBible;

use RuntimeException;

/**
 * A failure talking to API.Bible, classified so the reader can explain it
 * honestly. The message never contains the API key.
 */
class ApiBibleException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';
    public const UNAUTHORIZED = 'unauthorized';
    public const NOT_FOUND = 'not_found';
    public const RATE_LIMITED = 'rate_limited';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "API.Bible request failed: {$reason}");
    }
}
