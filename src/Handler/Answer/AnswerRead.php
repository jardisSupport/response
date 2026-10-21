<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\ReadResult;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers a single read: the one item as `@type` plus its public fields, or an empty answer when nothing was
 * found. Whether "nothing found" is a 404 is the door's call ({@see AnswerNotFound}).
 *
 * Atomic closure - one entry point.
 */
final class AnswerRead
{
    public function __construct(private readonly BuildResponseData $buildResponseData = new BuildResponseData())
    {
    }

    /**
     * @param ReadResult<mixed> $found
     * @param string $type the `@type` of the answer (first letter lower case)
     */
    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        ReadResult $found,
        string $type
    ): DomainResponseInterface {
        $result->setData(
            $found->isEmpty()
                ? []
                : ($this->buildResponseData)(ResponseStatus::Success, (object) $found->first(), null, $type)
        );

        return ($transform)($result);
    }
}
