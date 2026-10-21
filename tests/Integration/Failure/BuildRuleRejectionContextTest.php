<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Failure;

use JardisSupport\Response\Data\RuleResult;
use JardisSupport\Response\Handler\Failure\BuildRuleRejectionContext;
use JardisSupport\Response\Handler\Failure\FoldRuleSet;
use PHPUnit\Framework\TestCase;

/**
 * The context of a 422 rejection: key order and key precedence are fixed (`rule`, context, `set`).
 */
class BuildRuleRejectionContextTest extends TestCase
{
    public function testRuleComesFirstThenTheContextAndNoSetWithoutOne(): void
    {
        $rejection = RuleResult::reject('OrderMustBeOpen', 'order.rule.open', ['status' => 'closed', 'limit' => 5]);

        $this->assertSame(
            ['rule' => 'OrderMustBeOpen', 'status' => 'closed', 'limit' => 5],
            (new BuildRuleRejectionContext())($rejection),
        );
    }

    public function testASetIsAppendedLast(): void
    {
        $rejection = RuleResult::reject('OrderMustBeOpen', 'order.rule.open', ['status' => 'closed']);

        $this->assertSame(
            ['rule' => 'OrderMustBeOpen', 'status' => 'closed', 'set' => 'OrderIntakeChecks'],
            (new BuildRuleRejectionContext())($rejection, 'OrderIntakeChecks'),
        );
    }

    public function testAnEmptyContextYieldsTheRuleOnly(): void
    {
        $rejection = RuleResult::reject('OrderMustBeOpen', 'order.rule.open');

        $this->assertSame(['rule' => 'OrderMustBeOpen'], (new BuildRuleRejectionContext())($rejection));
        $this->assertSame(
            ['rule' => 'OrderMustBeOpen', 'set' => 'Checks'],
            (new BuildRuleRejectionContext())($rejection, 'Checks'),
        );
    }

    public function testTheRuleOfTheRejectionWinsAgainstAContextKeyRuleAndKeepsTheFirstPosition(): void
    {
        $rejection = RuleResult::reject('Real', 'k', ['status' => 'x', 'rule' => 'Foreign']);

        $this->assertSame(
            ['rule' => 'Real', 'status' => 'x'],
            (new BuildRuleRejectionContext())($rejection),
        );
    }

    public function testAContextKeySetWinsAgainstTheSetParameterAndKeepsItsPosition(): void
    {
        $rejection = RuleResult::reject('Real', 'k', ['set' => 'FromContext', 'status' => 'x']);

        $this->assertSame(
            ['rule' => 'Real', 'set' => 'FromContext', 'status' => 'x'],
            (new BuildRuleRejectionContext())($rejection, 'FromParameter'),
        );
    }

    public function testFoldRuleSetAppendsTheSetAndALaterContextKeyWins(): void
    {
        $fold = new FoldRuleSet();

        $this->assertSame(['a' => 1, 'set' => 'S'], $fold(['a' => 1], 'S'));
        $this->assertSame(['set' => 'Mine', 'a' => 1], $fold(['set' => 'Mine', 'a' => 1], 'S'));
        $this->assertSame(['set' => 'S'], $fold([], 'S'));
    }
}
