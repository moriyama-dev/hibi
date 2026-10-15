<?php

declare(strict_types=1);

namespace Hibi\Vendor\Jose\Component\Checker;

use DateTimeImmutable;
use Hibi\Vendor\Psr\Clock\ClockInterface;

/**
 * @internal
 */
final class InternalClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
