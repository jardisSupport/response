<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request\Accept;

use JardisSupport\Contract\Validation\ValueValidatorInterface;

/**
 * Accepts a path or query value as a calendar-valid Y-m-d.
 *
 * The form is judged by the injected value validator ({@see ValueValidatorInterface}, in practice the
 * `DateTime` validator of `jardissupport/validation`); a transport value is a string end to end, so the
 * accepted string is handed on unchanged. Atomic closure - one entry point.
 */
final class TransportDate
{
    public function __construct(private readonly ValueValidatorInterface $validator)
    {
    }

    public function __invoke(mixed $value): ?string
    {
        return is_string($value) && $this->validator->validateValue($value, ['format' => 'Y-m-d']) === null
            ? $value
            : null;
    }
}
