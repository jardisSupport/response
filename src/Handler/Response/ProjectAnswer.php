<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;

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
 * The closure knows no field name of its own - only the list it is given. The rebuild of the answer
 * is {@see ReshapeAnswer}'s; this closure only supplies the filter.
 *
 * Atomic closure - one entry point.
 */
final class ProjectAnswer
{
    public function __construct(
        private readonly ReshapeAnswer $reshapeAnswer = new ReshapeAnswer(),
    ) {
    }

    public function __invoke(DomainResponseInterface $response, string ...$fields): DomainResponseInterface
    {
        return ($this->reshapeAnswer)(
            $response,
            static fn (array $data): array => array_filter(
                $data,
                static fn (string|int $name): bool => $name === '@type' || in_array($name, $fields, true),
                ARRAY_FILTER_USE_KEY
            )
        );
    }
}
