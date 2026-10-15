<?php

declare(strict_types=1);

namespace Hibi\Vendor\GuzzleHttp\Promise;

/**
 * Exception that is set as the reason for a promise that has been cancelled.
 */
class CancellationException extends RejectionException
{
}
