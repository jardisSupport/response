<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a JSON body value as a boolean: `true` or `false` only. Atomic closure - one entry point.
 */
final class BodyBool
{
    public function __invoke(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
