<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a path or query value as a boolean: exactly `true`, `false`, `1` or `0`.
 * Atomic closure - one entry point.
 */
final class TransportBool
{
    public function __invoke(mixed $value): ?bool
    {
        return is_string($value) && in_array($value, ['true', 'false', '1', '0'], true)
            ? $value === 'true' || $value === '1'
            : null;
    }
}
