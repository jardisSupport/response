<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

/**
 * Outcome of one rule invocation: a pass, or a rejection that names the rule.
 *
 * A rejection is a value, not an exception: it is carried through a guard chain and answered as the 422
 * failure form (`{rule, messageKey, context}`, see {@see Failure\RuleViolation}) by
 * {@see \JardisSupport\Response\Handler\Answer\AnswerRuleViolation}. Structure and message key are stable
 * API across the versions of a rule.
 */
final readonly class RuleResult
{
    /**
     * @param array<string, mixed> $context
     */
    private function __construct(
        public bool $passed,
        public string $rule = '',
        public string $messageKey = '',
        public array $context = [],
    ) {
    }

    public static function pass(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function reject(string $rule, string $messageKey, array $context = []): self
    {
        return new self(false, $rule, $messageKey, $context);
    }
}
