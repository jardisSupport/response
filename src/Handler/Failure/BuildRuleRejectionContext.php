<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use JardisSupport\Response\Data\RuleResult;

/**
 * Builds the context of a 422 rule rejection: the rule that said no, the context of the rejection and,
 * when the rule belongs to a rule set, the name of the set.
 *
 * Key order is `rule`, the keys of the rejection, `set`; `rule` wins over a context key `rule`, a context
 * key `set` wins over the parameter. Atomic closure - one entry point.
 */
final readonly class BuildRuleRejectionContext
{
    public function __construct(
        private FoldRuleSet $foldRuleSet = new FoldRuleSet(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(RuleResult $rejection, ?string $set = null): array
    {
        $context = ['rule' => $rejection->rule] + $rejection->context;

        return $set === null ? $context : ($this->foldRuleSet)($context, $set);
    }
}
