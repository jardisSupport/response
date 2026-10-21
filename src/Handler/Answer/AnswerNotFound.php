<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Handler\Failure\NotFoundPayload;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers 404: the addressed aggregate does not exist; the context names what was looked up.
 *
 * Atomic closure - one entry point.
 */
final class AnswerNotFound
{
    public const string MESSAGE_KEY = 'notFound';

    public function __construct(
        private readonly NotFoundPayload $notFoundPayload = new NotFoundPayload(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    /**
     * @param array<string, mixed> $context the lookup (for example the business key that was asked for)
     */
    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        array $context
    ): DomainResponseInterface {
        $result->setData(($this->buildResponseData)(
            ResponseStatus::NotFound,
            ($this->notFoundPayload)(self::MESSAGE_KEY, $context),
            null,
            ''
        ));

        return ($transform)($result, ResponseStatus::NotFound);
    }
}
