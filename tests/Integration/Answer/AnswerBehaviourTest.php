<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Answer;

use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Contract\Repository\Exception\TransientConflictException;
use JardisSupport\Response\Data\InputFindings;
use JardisSupport\Response\Data\ReadResult;
use JardisSupport\Response\Data\Reference;
use JardisSupport\Response\Data\RuleResult;
use JardisSupport\Response\Handler\Answer\AnswerCommand;
use JardisSupport\Response\Handler\Answer\AnswerFindings;
use JardisSupport\Response\Handler\Answer\AnswerNotFound;
use JardisSupport\Response\Handler\Answer\AnswerOutcome;
use JardisSupport\Response\Handler\Answer\AnswerRead;
use JardisSupport\Response\Handler\Answer\AnswerRuleViolation;
use JardisSupport\Response\Handler\Answer\AnswerThrowable;
use JardisSupport\Response\Tests\Support\Outcome\AnswerHarness;
use JardisSupport\Response\Tests\Support\Outcome\FakeOutcome;
use PHPUnit\Framework\TestCase;

/**
 * What each answer closure puts into the result and the envelope, case by case.
 */
class AnswerBehaviourTest extends TestCase
{
    public function testFindingsAnswer400WithFieldsAndEveryValidatorTextAsError(): void
    {
        $fields = [['field' => 'email', 'reason' => 'missing', 'message' => 'Required', 'messageKey' => 'validation.missing']];
        $result = AnswerHarness::result('PlaceOrderHandler');

        $answer = (new AnswerFindings())($result, AnswerHarness::transform(), new InputFindings($fields, ['email is required']));

        $this->assertSame(400, $answer->getStatus());
        $this->assertSame(['@type' => 'validation', 'fields' => $fields], $answer->getData());
        $this->assertSame(['PlaceOrderHandler' => ['email is required']], $answer->getErrors());
    }

    public function testNotFoundAnswers404WithTheLookupAsContext(): void
    {
        $answer = (new AnswerNotFound())(AnswerHarness::result('X'), AnswerHarness::transform(), ['orderNumber' => 'A-1']);

        $this->assertSame(404, $answer->getStatus());
        $this->assertSame(['@type' => 'notFound', 'context' => ['orderNumber' => 'A-1']], $answer->getData());
    }

    public function testRuleViolationAnswers422WithRuleKeyAndContext(): void
    {
        $answer = (new AnswerRuleViolation())(
            AnswerHarness::result('X'),
            AnswerHarness::transform(),
            RuleResult::reject('OrderMustBeOpen', 'order.rule.open', ['status' => 'closed'])
        );

        $this->assertSame(422, $answer->getStatus());
        $this->assertSame(
            [
                '@type' => 'ruleViolation',
                'rule' => 'OrderMustBeOpen',
                'messageKey' => 'order.rule.open',
                'context' => ['status' => 'closed'],
            ],
            $answer->getData()
        );
    }

    public function testOutcomeCollectsDomainEventsAndErrorsBeforeTheData(): void
    {
        $event = new \stdClass();
        $outcome = new FakeOutcome(ResponseStatus::Success, (object) ['total' => 5], ['note'], [$event]);
        $result = AnswerHarness::result('RunHandler');

        $answer = (new AnswerOutcome())($result, AnswerHarness::transform(), $outcome, 'RunSomething');

        $this->assertSame(200, $answer->getStatus());
        $this->assertSame(['@type' => 'runSomething', 'total' => 5], $answer->getData());
        $this->assertSame(['RunHandler' => ['note']], $answer->getErrors());
        $this->assertSame(['RunHandler' => [$event]], $answer->getEvents(EventScope::Domain));
        $this->assertSame([], $answer->getEvents(EventScope::Internal));
    }

    public function testOutcomeOfARejectAnswersItsStatusAndTheDeclaredMessageKeyNextToTheContext(): void
    {
        $outcome = new FakeOutcome(ResponseStatus::NotFound, messageKey: 'order.gone', context: ['orderNumber' => 'A-1']);

        $answer = (new AnswerOutcome())(AnswerHarness::result('X'), AnswerHarness::transform(), $outcome, 'Run');

        $this->assertSame(404, $answer->getStatus());
        $this->assertSame(['@type' => 'notFound', 'context' => ['orderNumber' => 'A-1'], 'messageKey' => 'order.gone'], $answer->getData());
    }

    public function testThrowableOfTheOwnerAnswers500WithoutData(): void
    {
        $answer = (new AnswerThrowable())(
            AnswerHarness::result('X'),
            AnswerHarness::transform(),
            new \RuntimeException('boom'),
            true,
            'Order'
        );

        $this->assertSame(500, $answer->getStatus());
        $this->assertSame([], $answer->getData());
    }

    public function testThrowableOfTheOwnerAnswersTheStaleWriteAs409(): void
    {
        $answer = (new AnswerThrowable())(
            AnswerHarness::result('X'),
            AnswerHarness::transform(),
            new TransientConflictException('deadlock'),
            true,
            'Order'
        );

        $this->assertSame(409, $answer->getStatus());
        $this->assertSame('stale_write', $answer->getData()['reason']);
        $this->assertSame('Order', $answer->getData()['aggregate']);
    }

    public function testThrowableOfANestedRunIsThrownOnToItsOwner(): void
    {
        $failure = new \RuntimeException('boom');

        try {
            (new AnswerThrowable())(AnswerHarness::result('X'), AnswerHarness::transform(), $failure, false, 'Order');
            $this->fail('a nested run must throw the cause on');
        } catch (\RuntimeException $thrown) {
            $this->assertSame($failure, $thrown);
        }
    }

    public function testCommandAnswersTheReferenceWithTheNamedIdentifier(): void
    {
        $answer = (new AnswerCommand())(
            AnswerHarness::result('UpdateOrder'),
            AnswerHarness::transform(),
            new Reference('ORD-1', [], 'orderNumber'),
            'Order'
        );

        $this->assertSame(200, $answer->getStatus());
        $this->assertSame(['@type' => 'order', 'orderNumber' => 'ORD-1'], $answer->getData());
    }

    public function testReadAnswersTheItemAsTypeAndPublicFields(): void
    {
        $item = new class {
            public string $orderNumber = 'ORD-1';
            public int $total = 7;
        };

        $answer = (new AnswerRead())(AnswerHarness::result('X'), AnswerHarness::transform(), ReadResult::ofOne($item), 'order');

        $this->assertSame(['@type' => 'order', 'orderNumber' => 'ORD-1', 'total' => 7], $answer->getData());
    }

    public function testReadOfNothingAnswersEmptyData(): void
    {
        $answer = (new AnswerRead())(AnswerHarness::result('X'), AnswerHarness::transform(), ReadResult::ofOne(null), 'order');

        $this->assertSame(200, $answer->getStatus());
        $this->assertSame([], $answer->getData());
    }

    public function testRuleResultIsAValueAPassOrANamedRejection(): void
    {
        $pass = RuleResult::pass();
        $reject = RuleResult::reject('R', 'k', ['a' => 1]);

        $this->assertTrue($pass->passed);
        $this->assertSame(['', '', []], [$pass->rule, $pass->messageKey, $pass->context]);
        $this->assertFalse($reject->passed);
        $this->assertSame(['R', 'k', ['a' => 1]], [$reject->rule, $reject->messageKey, $reject->context]);
    }

    public function testInputFindingsFailedIsExactlyAnyFieldFinding(): void
    {
        $this->assertFalse((new InputFindings([], []))->failed());
        $this->assertFalse((new InputFindings([], ['text without a field']))->failed());
        $this->assertTrue((new InputFindings([['field' => 'a', 'reason' => 'invalid', 'message' => '', 'messageKey' => 'k']], []))->failed());
    }
}
