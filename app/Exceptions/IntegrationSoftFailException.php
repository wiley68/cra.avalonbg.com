<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Recoverable provider denial (missing scopes, forbidden, not found, rate limit).
 * Sync continues as succeeded with empty import results and last_error in the summary.
 */
class IntegrationSoftFailException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}
