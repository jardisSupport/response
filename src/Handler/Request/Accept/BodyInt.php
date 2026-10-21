<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a JSON body value as an integer: a JSON integer only (not `"1"`, not `1.5`).
 * Atomic closure - one entry point.
 */
final class BodyInt
{
    public function __invoke(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
