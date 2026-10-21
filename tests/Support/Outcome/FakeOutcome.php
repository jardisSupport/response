<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support\Outcome;

use JardisSupport\Contract\Kernel\AnswerableOutcomeInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;

/**
 * Stand-in for the outcome of a process run (the engine lives in another package): the five accessors of
 * {@see AnswerableOutcomeInterface} as plain values.
 */
final readonly class FakeOutcome implements AnswerableOutcomeInterface
{
    /**
     * @param list<string> $errors
     * @param list<object> $events
     * @param array<string, mixed> $context
     */
    public function __construct(
        private ResponseStatus $status,
        private object $response = new \stdClass(),
        private array $errors = [],
        private array $events = [],
        private ?string $messageKey = null,
        private array $context = [],
    ) {
    }

    public function status(): ResponseStatus
    {
        return $this->status;
    }

    public function isFailed(): bool
    {
        return $this->status->value >= 400;
    }

    public function response(): object
    {
        return $this->response;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function events(): array
    {
        return $this->events;
    }

    public function messageKey(): ?string
    {
        return $this->messageKey;
    }

    public function context(): array
    {
        return $this->context;
    }
}
