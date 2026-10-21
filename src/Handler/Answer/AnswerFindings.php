<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\InputFindings;
use JardisSupport\Response\Handler\Failure\ValidationPayload;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers 400: the failing fields of the input check, every validator text as an error.
 *
 * Atomic closure - one entry point.
 */
final class AnswerFindings
{
    public const string MESSAGE_KEY = 'validation';

    public function __construct(
        private readonly ValidationPayload $validationPayload = new ValidationPayload(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        InputFindings $findings
    ): DomainResponseInterface {
        foreach ($findings->errors as $error) {
            $result->addError($error);
        }
        $result->setData(($this->buildResponseData)(
            ResponseStatus::ValidationError,
            ($this->validationPayload)(self::MESSAGE_KEY, $findings->fields),
            null,
            ''
        ));

        return ($transform)($result, ResponseStatus::ValidationError);
    }
}
