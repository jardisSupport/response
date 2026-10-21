<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Reference;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers a finished write: the reference of the aggregate (its business identifier and the references of its
 * children), never the objects themselves. The caller loads what it needs through the read door.
 *
 * The status follows the errors collected in the result ({@see TransformResponse}): a write that collected none
 * answers 200.
 *
 * Atomic closure - one entry point.
 */
final class AnswerCommand
{
    public function __construct(private readonly BuildResponseData $buildResponseData = new BuildResponseData())
    {
    }

    /**
     * @param string $aggregate name of the written aggregate, the `@type` of the answer (first letter lower case)
     */
    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        Reference $reference,
        string $aggregate
    ): DomainResponseInterface {
        $result->setData(($this->buildResponseData)(ResponseStatus::Success, $reference, null, $aggregate));

        return ($transform)($result);
    }
}
