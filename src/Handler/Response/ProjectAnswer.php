<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\DomainResponse;

/**
 * Cuts the answer of a use case down to its outer contract.
 *
 * Inside, an answer may carry more than the outside promises (e.g. the internal
 * identifier next to the public key). At the outer door the 2xx answer keeps
 * `@type` plus exactly the named fields that exist in its data; status, errors,
 * metadata and the events of every scope pass through unchanged. A field that is
 * not in the data is not added; an unknown name is no error. Every answer that
 * is not 2xx is returned as the very same instance.
 *
 * The closure knows no field name of its own - only the list it is given.
 *
 * Atomic closure - one entry point.
 */
final class ProjectAnswer
{
    public function __invoke(DomainResponseInterface $response, string ...$fields): DomainResponseInterface
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
            array_filter(
                $response->getData(),
                static fn (string|int $name): bool => $name === '@type' || in_array($name, $fields, true),
                ARRAY_FILTER_USE_KEY
            ),
            [],
            $response->getErrors(),
            $response->getMetadata(),
            $eventsByScope
        );
    }
}
