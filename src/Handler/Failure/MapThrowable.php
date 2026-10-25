<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use JardisSupport\Contract\Kernel\ConcurrencyConflictExceptionInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Contract\Kernel\ValidationExceptionInterface;
use JardisSupport\Contract\Repository\Exception\TransientConflictException;
use JardisSupport\Response\Data\MappedFailure;

/**
 * The one table from a thrown failure to the answer of the use case.
 *
 * | thrown                                          | owner                  | not the owner                          |
 * |-------------------------------------------------|------------------------|----------------------------------------|
 * | TransientConflictException                      | 409 (`stale_write`)    | thrown on - the owner repeats the work |
 * | ConcurrencyConflictExceptionInterface (marker)  | 409 with its fields    | 409 with its fields                    |
 * | ValidationExceptionInterface (marker)           | 400 with its fields    | 400 with its fields                    |
 * | every other Throwable                           | 500, empty object      | thrown on                              |
 *
 * Every 4xx is always mapped, never thrown: a rejection is an answer, not an exception. Only the
 * owner (no transaction was open on entry) turns a 5xx cause into an answer; a nested run throws
 * it up to its owner, who logs it once ({@see LogCause}).
 */
final class MapThrowable
{
    public const string CONFLICT_KEY = 'process.transientConflict';
    public const string CONFLICT_REASON = 'stale_write';

    private const string MARKER_CONFLICT_KEY = 'concurrencyConflict';
    private const string MARKER_VALIDATION_KEY = 'validation';

    public function __construct(private readonly BuildFailureOutcome $buildFailureOutcome = new BuildFailureOutcome())
    {
    }

    /**
     * @throws \Throwable the failure itself, when it is not the owner's to answer
     */
    public function __invoke(\Throwable $failure, bool $owner, string $aggregate): MappedFailure
    {
        return match (true) {
            $failure instanceof TransientConflictException => $owner
                ? $this->answer(
                    ResponseStatus::Conflict,
                    self::CONFLICT_KEY,
                    ['reason' => self::CONFLICT_REASON, 'aggregate' => $aggregate]
                )
                : throw $failure,
            $failure instanceof ConcurrencyConflictExceptionInterface => $this->answer(
                ResponseStatus::Conflict,
                self::MARKER_CONFLICT_KEY,
                [
                    'reason' => $failure->getReason(),
                    'aggregate' => $failure->getAggregate(),
                    'violated' => $failure->getViolated(),
                ] + $failure->getContext()
            ),
            $failure instanceof ValidationExceptionInterface => $this->answer(
                ResponseStatus::ValidationError,
                self::MARKER_VALIDATION_KEY,
                ['fields' => $failure->getFields()]
            ),
            $owner => $this->answer(ResponseStatus::InternalError, ''),
            default => throw $failure,
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function answer(ResponseStatus $status, string $messageKey, array $context = []): MappedFailure
    {
        return new MappedFailure($status, ($this->buildFailureOutcome)($status, $messageKey, $context));
    }
}
