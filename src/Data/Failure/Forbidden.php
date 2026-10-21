<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data\Failure;

use JardisSupport\Contract\Kernel\ResponseStatus;

/**
 * 403 failure form: the actor is known but may not use the addressed endpoint.
 *
 * The message key and the context are data of the denial; the package defines no key value.
 */
final readonly class Forbidden implements FailureForm
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private string $messageKey,
        private array $context = [],
    ) {
    }

    public function status(): ResponseStatus
    {
        return ResponseStatus::Forbidden;
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
