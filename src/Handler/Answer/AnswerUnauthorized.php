<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Handler\Door\CheckActor;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers 401: the use case needs an actor and none is there; the answer carries no data.
 *
 * Atomic closure - one entry point.
 */
final class AnswerUnauthorized
{
    public function __construct(
        private readonly BuildFailureOutcome $buildFailureOutcome = new BuildFailureOutcome(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    public function __invoke(ContextResponseInterface $result, TransformResponse $transform): DomainResponseInterface
    {
        $response = ($this->buildFailureOutcome)(ResponseStatus::Unauthorized, CheckActor::MESSAGE_KEY);
        $result->setData(($this->buildResponseData)(ResponseStatus::Unauthorized, $response, null, ''));

        return ($transform)($result, ResponseStatus::Unauthorized);
    }
}
