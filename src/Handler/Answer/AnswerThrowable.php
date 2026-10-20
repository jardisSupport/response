<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Response\Handler\Failure\MapThrowable;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers a thrown failure: maps it ({@see MapThrowable}) to its status and failure form and answers that.
 *
 * Maps only; the cause is logged by {@see \JardisSupport\Response\Handler\Failure\LogFailure}, a separate step.
 * A failure the caller does not own to answer (a 5xx or the stale write of a nested run) is thrown on, as
 * {@see MapThrowable} decides.
 *
 * Atomic closure - one entry point.
 *
 * @throws \Throwable the failure itself, when it is not the owner's to answer
 */
final class AnswerThrowable
{
    public function __construct(
        private readonly MapThrowable $mapThrowable = new MapThrowable(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    /**
     * @param bool $owner true when no transaction was open on entry
     * @param string $aggregate the aggregate or process the failure came from (named by a 409 of a stale write)
     * @throws \Throwable
     */
    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        \Throwable $failure,
        bool $owner,
        string $aggregate
    ): DomainResponseInterface {
        $mapped = ($this->mapThrowable)($failure, $owner, $aggregate);
        $result->setData(($this->buildResponseData)($mapped->status, $mapped->response, null, ''));

        return ($transform)($result, $mapped->status);
    }
}
