<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a path or query value as an integer: the decimal digits of a whole number, optional
 * leading minus. No silent cast - `1.5`, `1e3` and ` 1` are refused. Atomic closure - one entry point.
 */
final class TransportInt
{
    public function __invoke(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1 ? (int) $value : null;
    }
}
