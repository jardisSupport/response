<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data\Failure;

use JardisSupport\Contract\Kernel\ResponseStatus;

/**
 * 403 failure form: the caller is known, but may not do this; the context names what was refused.
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
