<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Normalized failure talking to the external migration source (spec 0013):
 * a non-2xx response or a connection failure -> 502, a timeout -> 504
 * (mapped in BaseApiController::resolveExceptionStatus). The message is
 * ALWAYS a safe, static string set by App\Migrations\Support\
 * ExternalApiClient — never the request URL, the Bearer token, or the raw
 * upstream body — so it can be surfaced directly in the API error envelope
 * without leaking connection details.
 */
class ExternalApiException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}
