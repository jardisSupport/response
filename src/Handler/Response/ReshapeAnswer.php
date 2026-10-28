<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\DomainResponse;

/**
 * Reshapes the data of a 2xx answer and carries everything else over unchanged.
 *
 * The step the caller hands in receives the `data` of the answer and returns the new `data`. The
 * result is a new answer with the same status, errors, metadata and the events of every scope.
 * Every answer that is not 2xx is returned as the very same instance, the step is not called.
 *
 * The closure knows no field name and holds no rule of its own - what the data becomes is the
 * business of the step.
 *
 * Atomic closure - one entry point.
 */
final class ReshapeAnswer
{
    /**
     * @param \Closure(array<string, mixed>): array<string, mixed> $step receives the data, returns the new data
     */
    public function __invoke(DomainResponseInterface $response, \Closure $step): DomainResponseInterface
    {
        $status = $response->getStatus();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $eventsByScope = [];
        foreach (EventScope::cases() as $scope) {
            $scoped = $response->getEvents($scope);
            if ($scoped !== []) {
                $eventsByScope[$scope->value] = $scoped;
            }
        }

        return new DomainResponse(
            ResponseStatus::from($status),
            $step($response->getData()),
            [],
            $response->getErrors(),
            $response->getMetadata(),
            $eventsByScope
        );
    }
}
