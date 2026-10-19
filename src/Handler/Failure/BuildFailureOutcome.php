<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Failure\FailureForm;

/**
 * Builds the response of a failed use case - the one place every failure answer comes from.
 *
 * The response is the failure form of the status ({@see BuildFailureForm}). A 4xx without a form
 * answers with the message key and the context as they are; a reject of a node (`declared`)
 * answers the context alone as flat data, its key is carried next to it by the caller. A 5xx
 * answers an empty object.
 */
final class BuildFailureOutcome
{
    public function __construct(private readonly BuildFailureForm $buildFailureForm = new BuildFailureForm())
    {
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $errors
     */
    public function __invoke(
        ResponseStatus $status,
        string $messageKey,
        array $context = [],
        array $errors = [],
        bool $declared = false
    ): FailureForm|\stdClass {
        return ($this->buildFailureForm)($status, $messageKey, $context, $errors) ?? match (true) {
            $status->value >= 500 => new \stdClass(),
            $declared => (object) $context,
            default => (object) ['messageKey' => $messageKey, 'context' => $context],
        };
    }
}
