<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Failure\Conflict;
use JardisSupport\Response\Data\Failure\FailureForm;
use JardisSupport\Response\Data\Failure\NotFound;
use JardisSupport\Response\Data\Failure\RuleViolation;
use JardisSupport\Response\Data\Failure\ValidationFailure;
use JardisSupport\Response\Data\Reference;
use stdClass;

/**
 * Builds the `data` of the response envelope from the answer of a use case.
 *
 * The one place the answer of a use case is turned into envelope data:
 * - success: `@type` (the process name, first letter lower case) plus the public fields of the response;
 *   for a write answer ({@see Reference}) `@type`, the root under the name of its business identifier, then
 *   the child references under their keys;
 * - 400 `validation`: `@type`, `fields`; 404 `notFound`: `@type`, `context`;
 *   409 `concurrencyConflict`: `@type`, `reason`, `aggregate`, `context`, `violated`;
 *   422 `ruleViolation`: `@type`, `rule`, `messageKey`, `context`;
 * - the message key a node declared with its reject joins the data of every 4xx, the keys of the
 *   package's own door steps do not; the 422 form always carries its own; an empty `context`
 *   encodes as `{}`;
 * - another 4xx of a node (declared; 401 and 403 included) passes its response fields through
 *   unchanged; an undeclared reject and a 5xx answer no data.
 *
 * The data is not yet wire data: date values stay as they are, the projection to the wire
 * (`ProjectWire` of the data package) is the caller's step.
 *
 * Atomic closure - one entry point.
 */
final class BuildResponseData
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        ResponseStatus $status,
        object $response,
        ?string $declaredMessageKey,
        string $processName
    ): array {
        $data = match (true) {
            $response instanceof FailureForm => $this->failure($response),
            $status->value >= 400 => $declaredMessageKey === null ? [] : get_object_vars($response),
            $response instanceof Reference => ['@type' => lcfirst($processName)]
                + [$response->identifier() => $response->root()]
                + $response->children(),
            default => ['@type' => lcfirst($processName)] + get_object_vars($response),
        };

        if ($declaredMessageKey !== null && $declaredMessageKey !== '' && $status->value >= 400) {
            $data['messageKey'] = $declaredMessageKey;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(FailureForm $form): array
    {
        return match (true) {
            $form instanceof ValidationFailure => ['@type' => 'validation', 'fields' => $form->fields()],
            $form instanceof NotFound => ['@type' => 'notFound', 'context' => $this->context($form)],
            $form instanceof Conflict => [
                '@type' => 'concurrencyConflict',
                'reason' => (string) $form->reason(),
                'aggregate' => (string) $form->aggregate(),
                'context' => $this->context($form),
                'violated' => $form->violated(),
            ],
            $form instanceof RuleViolation => [
                '@type' => 'ruleViolation',
                'rule' => $form->rule(),
                'messageKey' => $form->messageKey(),
                'context' => $this->context($form),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|stdClass
     */
    private function context(FailureForm $form): array|stdClass
    {
        return $form->context() ?: new stdClass();
    }
}
