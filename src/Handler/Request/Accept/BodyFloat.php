<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a JSON body value as a float: a JSON number (integer or fraction), never a string.
 * Atomic closure - one entry point.
 */
final class BodyFloat
{
    public function __invoke(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
