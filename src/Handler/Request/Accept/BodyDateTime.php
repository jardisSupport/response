<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

use DateTimeImmutable;
use DateTimeZone;
use JardisSupport\Contract\Validation\ValueValidatorInterface;

/**
 * Accepts a JSON body value as an RFC 3339 instant (offset mandatory, `Z` and fractions allowed) and
 * normalises it to UTC right here - the one place a wire string becomes an instant, so no offset is
 * silently dropped on the way to the store.
 *
 * The form is judged by the injected value validator ({@see ValueValidatorInterface}, in practice the
 * `DateTime` validator of `jardissupport/validation`).
 * Atomic closure - one entry point.
 */
final class BodyDateTime
{
    public function __construct(private readonly ValueValidatorInterface $validator)
    {
    }

    public function __invoke(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $this->validator->validateValue($value, ['rfc3339' => true]) === null
            ? (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))
            : null;
    }
}
