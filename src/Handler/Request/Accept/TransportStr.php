<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a path or query value as a text: a non-empty string. Atomic closure - one entry point.
 */
final class TransportStr
{
    public function __invoke(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
