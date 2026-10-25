<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Failure;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Failure\Conflict;
use JardisSupport\Response\Data\Failure\NotFound;
use JardisSupport\Response\Data\Failure\RuleViolation;
use JardisSupport\Response\Data\Failure\ValidationFailure;
use JardisSupport\Response\Handler\Failure\BuildFailureForm;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Failure\ConcurrencyConflictPayload;
use JardisSupport\Response\Handler\Failure\NotFoundPayload;
use JardisSupport\Response\Handler\Failure\RuleViolationPayload;
use JardisSupport\Response\Handler\Failure\ValidationPayload;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use PHPUnit\Framework\TestCase;

/**
 * BuildFailureForm and the four payload closures build the 400/404/409/422 forms (W8, W9);
 * BuildFailureOutcome answers with them.
 */
class BuildFailureFormTest extends TestCase
{
    public function testValidationPayloadBuildsTheValidationFailure(): void
    {
        $fields = [
            ['field' => 'email', 'reason' => 'missing', 'message' => 'Required', 'messageKey' => 'order.email.missing'],
            ['field' => 'items[1].qty', 'reason' => 'invalid', 'message' => 'Too small'],
        ];

        $form = (new ValidationPayload())('order.invalid', $fields, ['form' => 'x']);

        $this->assertInstanceOf(ValidationFailure::class, $form);
        $this->assertSame(ResponseStatus::ValidationError, $form->status());
        $this->assertSame('order.invalid', $form->messageKey());
        $this->assertSame($fields, $form->fields());
        $this->assertSame(['form' => 'x'], $form->context());
    }

    public function testNotFoundPayloadBuildsTheNotFound(): void
    {
        $form = (new NotFoundPayload())('order.notFound', ['orderNumber' => 'ORD-404']);

        $this->assertInstanceOf(NotFound::class, $form);
        $this->assertSame('order.notFound', $form->messageKey());
        $this->assertSame(['orderNumber' => 'ORD-404'], $form->context());
    }

    public function testConcurrencyConflictPayloadBuildsTheConflict(): void
    {
        $form = (new ConcurrencyConflictPayload())('order.stale', 'stale', 'order', ['orderNumber' => 'O-1'], ['uq_order']);

        $this->assertInstanceOf(Conflict::class, $form);
        $this->assertSame('order.stale', $form->messageKey());
        $this->assertSame('stale', $form->reason());
        $this->assertSame('order', $form->aggregate());
        $this->assertSame(['orderNumber' => 'O-1'], $form->context());
        $this->assertSame(['uq_order'], $form->violated());
    }

    public function testRuleViolationPayloadBuildsTheRuleViolation(): void
    {
        $form = (new RuleViolationPayload())('OrderMustBeOpen', 'order.rule.open', ['id' => 1]);

        $this->assertInstanceOf(RuleViolation::class, $form);
        $this->assertSame('OrderMustBeOpen', $form->rule());
        $this->assertSame('order.rule.open', $form->messageKey());
        $this->assertSame(['id' => 1], $form->context());
    }

    public function testBuildFailureFormMapsTheFourStatusesToTheirForms(): void
    {
        $build = new BuildFailureForm();
        $fields = [['field' => 'a', 'reason' => 'missing', 'message' => 'm', 'messageKey' => 'k']];

        $validation = $build(ResponseStatus::ValidationError, 'v.key', ['fields' => $fields, 'extra' => 1], []);
        $notFound = $build(ResponseStatus::NotFound, 'n.key', ['orderNumber' => 'X'], []);
        $conflict = $build(
            ResponseStatus::Conflict,
            'c.key',
            ['reason' => 'stale', 'aggregate' => 'order', 'violated' => ['uq'], 'expectedVersion' => 3],
            []
        );
        $rule = $build(ResponseStatus::RuleViolation, 'r.key', ['rule' => 'Open', 'id' => 7], []);

        $this->assertEquals(new ValidationFailure('v.key', $fields, ['extra' => 1]), $validation);
        $this->assertEquals(new NotFound('n.key', ['orderNumber' => 'X']), $notFound);
        $this->assertEquals(new Conflict('c.key', ['expectedVersion' => 3], 'stale', 'order', ['uq']), $conflict);
        $this->assertEquals(new RuleViolation('Open', 'r.key', ['id' => 7]), $rule);
    }

    public function testStatusesWithoutAFormGiveNull(): void
    {
        $build = new BuildFailureForm();

        foreach ([ResponseStatus::Unauthorized, ResponseStatus::from(403), ResponseStatus::MethodNotAllowed] as $status) {
            $this->assertNull($build($status, 'k', [], []), $status->name);
        }
    }

    public function testValidationWithoutFieldsReportsEachErrorAsAFinding(): void
    {
        $form = (new BuildFailureForm())(ResponseStatus::ValidationError, 'v', [], ['first', 'second']);

        $this->assertInstanceOf(ValidationFailure::class, $form);
        $this->assertSame(
            [
                ['field' => '', 'reason' => 'invalid', 'message' => 'first'],
                ['field' => '', 'reason' => 'invalid', 'message' => 'second'],
            ],
            $form->fields()
        );
    }

    public function testFieldErrorsOfACalleeKeepTheirStructureAllTheWayToTheOutcome(): void
    {
        $fields = [
            ['field' => 'items[1].qty', 'reason' => 'invalid', 'message' => 'Too small', 'messageKey' => 'order.qty.min'],
            ['field' => 'email', 'reason' => 'missing', 'message' => 'Required'],
        ];

        $form = (new BuildFailureOutcome())(ResponseStatus::ValidationError, '', ['fields' => $fields], ['Too small']);

        $this->assertInstanceOf(ValidationFailure::class, $form);
        $this->assertSame('', $form->messageKey(), 'the 400 form has no key of its own; the callee key is not carried');
        $this->assertSame($fields, $form->fields());
        $this->assertSame([], $form->context());
    }

    public function testBuildOutcomeAnswersWithTheFormOfEachRejectStatus(): void
    {
        $cases = [
            [ResponseStatus::NotFound, NotFound::class],
            [ResponseStatus::Conflict, Conflict::class],
            [ResponseStatus::RuleViolation, RuleViolation::class],
            [ResponseStatus::ValidationError, ValidationFailure::class],
        ];

        foreach ($cases as [$status, $class]) {
            $response = (new BuildFailureOutcome())($status, 'p.key');

            $this->assertInstanceOf($class, $response, $status->name);
            $this->assertSame($status, $response->status(), $status->name);
        }
    }

    public function testRejectWithoutAFormAnswersItsContextAsFlatData(): void
    {
        $response = (new BuildFailureOutcome())(ResponseStatus::MethodNotAllowed, 'p.refused', ['role' => 'guest'], [], true);

        $this->assertEquals((object) ['role' => 'guest'], $response);
        $this->assertSame(
            ['role' => 'guest', 'messageKey' => 'p.refused'],
            (new BuildResponseData())(ResponseStatus::MethodNotAllowed, $response, 'p.refused', 'P'),
            'the declared key joins the data last, see BuildResponseData'
        );
    }

    public function testAnUndeclaredRejectWithoutAFormAnswersKeyAndContext(): void
    {
        $response = (new BuildFailureOutcome())(ResponseStatus::MethodNotAllowed, 'p.refused', ['role' => 'guest']);

        $this->assertEquals((object) ['messageKey' => 'p.refused', 'context' => ['role' => 'guest']], $response);
    }

    public function testA5xxAnswersAnEmptyObjectEvenWithAContext(): void
    {
        $response = (new BuildFailureOutcome())(ResponseStatus::InternalError, 'x', ['secret' => 'no'], [], true);

        $this->assertEquals(new \stdClass(), $response);
    }
}
