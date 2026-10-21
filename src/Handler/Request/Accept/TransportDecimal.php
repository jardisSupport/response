<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

/**
 * Accepts a path or query value as a decimal: the string stays a string - never cast to float, exact
 * to the last digit - and must match the anchored pattern of the decimal (default: sign, digits,
 * optional fraction; a column limit is passed as `$pattern`, without delimiters, and without a `/`).
 *
 * Atomic closure - one entry point.
 */
final class TransportDecimal
{
    public const string LEXICAL_PATTERN = '^-?\d+(\.\d+)?$';

    public function __construct(private readonly string $pattern = self::LEXICAL_PATTERN)
    {
    }

    /**
     * @return numeric-string|null
     */
    public function __invoke(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/' . $this->pattern . '/', $value) !== 1) {
            return null;
        }
        /** @var numeric-string $value */
        return $value;
    }
}
