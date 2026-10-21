<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

/**
 * The findings of an input check: the failing fields and the texts the validator delivered.
 *
 * Result of {@see \JardisSupport\Response\Handler\Door\ValidateInput}. The input is valid exactly
 * when `fields === []`.
 */
final readonly class InputFindings
{
    /**
     * @param list<array{field: string, reason: string, message: string, messageKey: string}> $fields
     * @param list<string> $errors
     */
    public function __construct(
        public array $fields,
        public array $errors,
    ) {
    }

    /**
     * True when at least one field failed - the door then answers 400.
     */
    public function failed(): bool
    {
        return $this->fields !== [];
    }
}
