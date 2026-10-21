<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a path or query value as a float: a numeric string. Atomic closure - one entry point.
 */
final class TransportFloat
{
    public function __invoke(mixed $value): ?float
    {
        return is_string($value) && is_numeric($value) ? (float) $value : null;
    }
}
