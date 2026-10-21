<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

use DateTimeImmutable;
use JardisSupport\Contract\Validation\ValueValidatorInterface;

/**
 * Accepts a JSON body value as a calendar-valid `Y-m-d` and hands it on as the instant.
 *
 * The form is judged by the injected value validator ({@see ValueValidatorInterface}, in practice the
 * `DateTime` validator of `jardissupport/validation`).
 * Atomic closure - one entry point.
 */
final class BodyDate
{
    public function __construct(private readonly ValueValidatorInterface $validator)
    {
    }

    public function __invoke(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $this->validator->validateValue($value, ['format' => 'Y-m-d']) === null
            ? new DateTimeImmutable($value)
            : null;
    }
}
