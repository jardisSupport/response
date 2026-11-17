<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Handler\Failure\ForbiddenPayload;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers 403: the caller may not do this; the context names what was refused.
 *
 * The answer is a value, not an exception: the payload sits in `data` like the 404, `errors` stays empty.
 * It is not the door rejection of the delivery layer (`RequireRight`).
 *
 * Atomic closure - one entry point.
 */
final class AnswerForbidden
{
    public const string MESSAGE_KEY = 'forbidden';

    public function __construct(
        private readonly ForbiddenPayload $forbiddenPayload = new ForbiddenPayload(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    /**
     * @param array<string, mixed> $context what was refused (for example the right that is missing)
     */
    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        array $context
    ): DomainResponseInterface {
        $result->setData(($this->buildResponseData)(
            ResponseStatus::Forbidden,
            ($this->forbiddenPayload)(self::MESSAGE_KEY, $context),
            null,
            ''
        ));

        return ($transform)($result, ResponseStatus::Forbidden);
    }
}
