<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a JSON body value as a text: any string, the empty one included. Strict - a JSON number never
 * satisfies a text. Atomic closure - one entry point.
 */
final class BodyStr
{
    public function __invoke(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
