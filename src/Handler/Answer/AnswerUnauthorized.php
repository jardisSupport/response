<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers 401: the request carries no actor (the door in front of the use case found none); the
 * answer carries no data, the message key stays internal.
 *
 * Atomic closure - one entry point.
 */
final class AnswerUnauthorized
{
    public const string MESSAGE_KEY = 'process.actorMissing';

    public function __construct(
        private readonly BuildFailureOutcome $buildFailureOutcome = new BuildFailureOutcome(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    public function __invoke(ContextResponseInterface $result, TransformResponse $transform): DomainResponseInterface
    {
        $response = ($this->buildFailureOutcome)(ResponseStatus::Unauthorized, self::MESSAGE_KEY);
        $result->setData(($this->buildResponseData)(ResponseStatus::Unauthorized, $response, null, ''));

        return ($transform)($result, ResponseStatus::Unauthorized);
    }
}
