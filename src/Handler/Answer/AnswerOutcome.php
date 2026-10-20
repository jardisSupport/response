<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\AnswerableOutcomeInterface;
use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers the outcome of a finished process run: its Domain events and error texts into the result, then the
 * typed response (success) or the failure form of the reject (4xx) as data under the status of the outcome.
 *
 * The outcome is read through {@see AnswerableOutcomeInterface}, so the package knows no process engine. A reject
 * answers the context of the node as flat data and its declared message key (see {@see BuildFailureOutcome}).
 *
 * Atomic closure - one entry point.
 */
final class AnswerOutcome
{
    public function __construct(
        private readonly BuildFailureOutcome $buildFailureOutcome = new BuildFailureOutcome(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        AnswerableOutcomeInterface $outcome,
        string $processName
    ): DomainResponseInterface {
        foreach ($outcome->events() as $event) {
            $result->addEvent($event, EventScope::Domain);
        }
        foreach ($outcome->errors() as $error) {
            $result->addError($error);
        }
        $status = $outcome->status();
        $response = $outcome->isFailed()
            ? ($this->buildFailureOutcome)(
                $status,
                (string) $outcome->messageKey(),
                $outcome->context(),
                $outcome->errors(),
                true
            )
            : $outcome->response();
        $result->setData(($this->buildResponseData)($status, $response, $outcome->messageKey(), $processName));

        return ($transform)($result, $status);
    }
}
