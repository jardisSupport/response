<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\ContextResponseInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\RuleResult;
use JardisSupport\Response\Handler\Failure\RuleViolationPayload;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;

/**
 * Answers 422: a business rule rejected the request; the answer names the rule that said no.
 *
 * Atomic closure - one entry point.
 */
final class AnswerRuleViolation
{
    public function __construct(
        private readonly RuleViolationPayload $ruleViolationPayload = new RuleViolationPayload(),
        private readonly BuildResponseData $buildResponseData = new BuildResponseData(),
    ) {
    }

    public function __invoke(
        ContextResponseInterface $result,
        TransformResponse $transform,
        RuleResult $rejection
    ): DomainResponseInterface {
        $result->setData(($this->buildResponseData)(
            ResponseStatus::RuleViolation,
            ($this->ruleViolationPayload)($rejection->rule, $rejection->messageKey, $rejection->context),
            null,
            ''
        ));

        return ($transform)($result, ResponseStatus::RuleViolation);
    }
}
