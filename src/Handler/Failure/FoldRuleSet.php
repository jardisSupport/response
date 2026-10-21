<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

/**
 * Folds the name of the rule set into the context of a rejection.
 *
 * Atomic closure - one entry point. A key `set` already present in the context wins (array union).
 */
final readonly class FoldRuleSet
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function __invoke(array $context, string $set): array
    {
        return $context + ['set' => $set];
    }
}
