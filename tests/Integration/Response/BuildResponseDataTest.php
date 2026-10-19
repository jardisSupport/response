<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Failure\Conflict;
use JardisSupport\Response\Data\Failure\NotFound;
use JardisSupport\Response\Data\Failure\RuleViolation;
use JardisSupport\Response\Data\Failure\ValidationFailure;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Tests\Support\Response\ReserveStockResponse;
use stdClass;

use PHPUnit\Framework\TestCase;

/**
 * `data` of the envelope per answer form, from the single values a door hands in. The forms are
 * the ones of the pre-move generated payload closures (builder commit e72b2b528, see
 * EnvelopeParityTest); the byte parity of the whole envelope is proved there. The wire projection
 * (dates) is not this step's job: values come back as they went in.
 */
class BuildResponseDataTest extends TestCase
{
    /**
     * The data of a reject as the door builds it: failure form of the status, the key declared.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function rejectData(ResponseStatus $status, string $key, array $context = []): array
    {
        $response = (new BuildFailureOutcome())($status, $key, $context, [], true);

        return (new BuildResponseData())($status, $response, $key, 'CheckoutOrder');
    }

    public function testSuccessWithResponseAnswersTypeAndPublicFields(): void
    {
        $response = new ReserveStockResponse('R-1', 3);

        $this->assertSame(
            ['@type' => 'checkoutOrder', 'reservationId' => 'R-1', 'quantity' => 3, 'note' => 'none'],
            (new BuildResponseData())(ResponseStatus::Success, $response, null, 'CheckoutOrder')
        );
    }

    public function testSuccessWithoutResponseAnswersTypeAndNodeData(): void
    {
        $this->assertSame(
            ['@type' => 'checkoutOrder', 'state' => 'done'],
            (new BuildResponseData())(ResponseStatus::Success, (object) ['state' => 'done'], null, 'CheckoutOrder')
        );
    }

    public function testSuccessWithoutAnyDataAnswersOnlyTheType(): void
    {
        $this->assertSame(
            ['@type' => 'checkoutOrder'],
            (new BuildResponseData())(ResponseStatus::Success, new stdClass(), null, 'CheckoutOrder')
        );
    }

    public function testTheTypeStaysFirstWhenTheResponseNamesItsOwn(): void
    {
        $data = (new BuildResponseData())(ResponseStatus::Success, (object) ['@type' => 'other', 'a' => 1], null, 'CheckoutOrder');

        $this->assertSame(['@type' => 'checkoutOrder', 'a' => 1], $data);
    }

    public function testValidationAnswersTypeAndFieldsWithPaths(): void
    {
        $fields = [
            ['field' => 'lines[1].qty', 'reason' => 'min', 'message' => 'Must be at least 1', 'messageKey' => 'q.min'],
            ['field' => 'email', 'reason' => 'missing', 'message' => 'Required'],
        ];
        $form = new ValidationFailure('process.inputInvalid', $fields, ['extra' => 'dropped']);

        $data = (new BuildResponseData())(ResponseStatus::ValidationError, $form, null, 'CheckoutOrder');

        $this->assertSame(['@type' => 'validation', 'fields' => $fields], $data);
    }

    public function testNotFoundAnswersTypeAndContext(): void
    {
        $form = new NotFound('order.notFound', ['orderNumber' => 'ORD-404']);

        $data = (new BuildResponseData())(ResponseStatus::NotFound, $form, null, 'CheckoutOrder');

        $this->assertSame(['@type' => 'notFound', 'context' => ['orderNumber' => 'ORD-404']], $data);
    }

    public function testNotFoundWithoutContextAnswersAnEmptyObject(): void
    {
        $data = (new BuildResponseData())(ResponseStatus::NotFound, new NotFound('order.notFound'), null, 'CheckoutOrder');

        $this->assertEquals(new stdClass(), $data['context']);
        $this->assertStringContainsString('"context":{}', (string) json_encode($data));
    }

    public function testConflictAnswersReasonAggregateContextAndViolated(): void
    {
        $form = new Conflict('order.conflict', ['expectedVersion' => 3], 'stale', 'order', ['uq_order_number']);

        $data = (new BuildResponseData())(ResponseStatus::Conflict, $form, null, 'CheckoutOrder');

        $this->assertSame(
            [
                '@type' => 'concurrencyConflict',
                'reason' => 'stale',
                'aggregate' => 'order',
                'context' => ['expectedVersion' => 3],
                'violated' => ['uq_order_number'],
            ],
            $data
        );
    }

    public function testConflictWithoutNamedReasonAndAggregateAnswersEmptyTexts(): void
    {
        $form = new Conflict('process.transientConflict', [], null, null, []);

        $data = (new BuildResponseData())(ResponseStatus::Conflict, $form, null, 'CheckoutOrder');

        $this->assertSame('', $data['reason']);
        $this->assertSame('', $data['aggregate']);
        $this->assertEquals(new stdClass(), $data['context']);
        $this->assertSame([], $data['violated']);
    }

    public function testRuleViolationAnswersRuleMessageKeyAndContext(): void
    {
        $form = new RuleViolation('OrderMustBeOpen', 'order.rule.open', ['status' => 'closed']);

        $data = (new BuildResponseData())(ResponseStatus::RuleViolation, $form, null, 'CheckoutOrder');

        $this->assertSame(
            [
                '@type' => 'ruleViolation',
                'rule' => 'OrderMustBeOpen',
                'messageKey' => 'order.rule.open',
                'context' => ['status' => 'closed'],
            ],
            $data
        );
    }

    public function testRejectOfARealRunAnswersItsFormData(): void
    {
        $status = ResponseStatus::Conflict;
        $response = (new BuildFailureOutcome())(
            $status,
            'order.clash',
            ['reason' => 'stale', 'aggregate' => 'order', 'violated' => ['uq'], 'orderNumber' => 'O-1'],
            [],
            true
        );

        $data = (new BuildResponseData())($status, $response, 'order.clash', 'CheckoutOrder');

        $this->assertSame(409, $response->status()->value);
        $this->assertSame(
            [
                '@type' => 'concurrencyConflict',
                'reason' => 'stale',
                'aggregate' => 'order',
                'context' => ['orderNumber' => 'O-1'],
                'violated' => ['uq'],
                'messageKey' => 'order.clash',
            ],
            $data
        );
    }

    public function testOtherClientErrorPassesItsResponseThroughWithoutType(): void
    {
        $this->assertSame(
            ['role' => 'guest', 'messageKey' => 'order.forbidden'],
            $this->rejectData(ResponseStatus::Forbidden, 'order.forbidden', ['role' => 'guest'])
        );
    }

    public function testServerErrorAnswersNoData(): void
    {
        $this->assertSame(
            [],
            (new BuildResponseData())(ResponseStatus::InternalError, new stdClass(), null, 'CheckoutOrder')
        );
    }

    public function testDeclaredKeyOfTheRejectingNodeJoinsEveryClientErrorAfterTheForm(): void
    {
        $cases = [
            [ResponseStatus::NotFound, ['orderNumber' => 'X'], ['@type' => 'notFound', 'context' => ['orderNumber' => 'X']]],
            [
                ResponseStatus::ValidationError,
                ['fields' => [['field' => 'a', 'reason' => 'invalid', 'message' => 'm']]],
                ['@type' => 'validation', 'fields' => [['field' => 'a', 'reason' => 'invalid', 'message' => 'm']]],
            ],
            [
                ResponseStatus::Conflict,
                ['reason' => 'stale', 'aggregate' => 'order'],
                [
                    '@type' => 'concurrencyConflict',
                    'reason' => 'stale',
                    'aggregate' => 'order',
                    'context' => new stdClass(),
                    'violated' => [],
                ],
            ],
            [
                ResponseStatus::RuleViolation,
                ['rule' => 'OrderMustBeOpen'],
                ['@type' => 'ruleViolation', 'rule' => 'OrderMustBeOpen', 'messageKey' => 'order.declared', 'context' => new stdClass()],
            ],
        ];

        foreach ($cases as [$status, $context, $form]) {
            $data = $this->rejectData($status, 'order.declared', $context);

            $expected = $status === ResponseStatus::RuleViolation ? $form : $form + ['messageKey' => 'order.declared'];
            $this->assertEquals($expected, $data, $status->name);
            if ($status !== ResponseStatus::RuleViolation) {
                $this->assertSame('messageKey', array_key_last($data), $status->name);
            }
        }
    }

    public function testDeclaredKeyOfAClientErrorWithoutFormIsKept(): void
    {
        $this->assertSame(
            ['messageKey' => 'order.forbidden'],
            $this->rejectData(ResponseStatus::Forbidden, 'order.forbidden')
        );
    }

    public function testNoKeyIsDeclaredForSuccessOrAnEmptyKey(): void
    {
        $notFound = (new BuildResponseData())(
            ResponseStatus::NotFound,
            (new BuildFailureOutcome())(ResponseStatus::NotFound, '', [], [], true),
            '',
            'CheckoutOrder'
        );
        $success = (new BuildResponseData())(ResponseStatus::Success, (object) ['a' => 1], null, 'CheckoutOrder');

        $this->assertArrayNotHasKey('messageKey', $notFound);
        $this->assertSame(['@type' => 'checkoutOrder', 'a' => 1], $success);
    }

    public function testKeysOfThePackageLayersNeverReachTheData(): void
    {
        $response = (new BuildFailureOutcome())(ResponseStatus::NotFound, 'process.something', ['k' => 'v']);

        $this->assertSame(
            ['@type' => 'notFound', 'context' => ['k' => 'v']],
            (new BuildResponseData())(ResponseStatus::NotFound, $response, null, 'CheckoutOrder')
        );
    }

    public function testRejectOfAnInputNodeAnswersFieldPathsBelowTheInputProperty(): void
    {
        $fields = [
            ['field' => 'customer.email', 'reason' => 'missing', 'message' => 'Required', 'messageKey' => 'k.missing'],
            ['field' => 'customer.address.zip', 'reason' => 'invalid', 'message' => 'Bad'],
        ];

        $this->assertSame(
            ['@type' => 'validation', 'fields' => $fields, 'messageKey' => 'customer.invalid'],
            $this->rejectData(ResponseStatus::ValidationError, 'customer.invalid', ['fields' => $fields])
        );
    }

    public function testRejectOfANodeWithoutInputPropertyKeepsItsFieldPaths(): void
    {
        $fields = [['field' => 'email', 'reason' => 'missing', 'message' => 'Required']];

        $data = $this->rejectData(ResponseStatus::ValidationError, 'x.invalid', ['fields' => $fields]);

        $this->assertSame($fields, $data['fields']);
    }

    public function testInputPropertyDoesNotTouchOtherStatuses(): void
    {
        $data = $this->rejectData(ResponseStatus::NotFound, 'customer.notFound', ['fields' => 'kept', 'id' => 1]);

        $this->assertSame(['fields' => 'kept', 'id' => 1], $data['context']);
    }

    public function testTopLevelDateStaysADateObjectForTheWireProjection(): void
    {
        $expires = new \DateTimeImmutable('2026-10-17T12:30:45+02:00');

        $data = (new BuildResponseData())(ResponseStatus::Success, (object) ['accessToken' => 't', 'expiresAt' => $expires], null, 'Login');

        $this->assertSame($expires, $data['expiresAt']);
        $this->assertSame(['@type' => 'login', 'accessToken' => 't', 'expiresAt' => $expires], $data);
    }

    public function testMutableDateTimeAndUtcStayAsGivenToo(): void
    {
        $at = new \DateTime('2026-01-02 03:04:05', new \DateTimeZone('UTC'));

        $data = (new BuildResponseData())(ResponseStatus::Success, (object) ['at' => $at], null, 'P');

        $this->assertSame($at, $data['at']);
    }

    public function testNestedDatesInArraysListsAndObjectsStayAsGiven(): void
    {
        $day = new \DateTimeImmutable('2026-03-04T05:06:07+00:00');
        $inner = new stdClass();
        $inner->since = $day;
        $inner->label = 'x';

        $data = (new BuildResponseData())(ResponseStatus::Success, (object) [
            'session' => ['issuedAt' => $day, 'history' => [$day, ['again' => $day]]],
            'owner' => $inner,
        ], null, 'P');

        $this->assertSame(['issuedAt' => $day, 'history' => [$day, ['again' => $day]]], $data['session']);
        $this->assertSame($inner, $data['owner']);
        $this->assertSame($day, $inner->since, 'the response object itself is not touched');
    }

    public function testNullDateAndNonDateValuesPassThroughUnchanged(): void
    {
        $plain = new stdClass();
        $plain->a = 1;
        $enum = ResponseStatus::Success;

        $data = (new BuildResponseData())(ResponseStatus::Success, (object) [
            'expiresAt' => null,
            'count' => 3,
            'tags' => ['a', 'b'],
            'plain' => $plain,
            'status' => $enum,
        ], null, 'P');

        $this->assertNull($data['expiresAt']);
        $this->assertSame(3, $data['count']);
        $this->assertSame(['a', 'b'], $data['tags']);
        $this->assertSame($plain, $data['plain'], 'an object without a date comes back as the same instance');
        $this->assertSame($enum, $data['status'], 'an enum keeps its own wire form');
    }

    public function testJsonSerializableKeepsItsOwnWireForm(): void
    {
        $value = new class implements \JsonSerializable {
            public \DateTimeImmutable $hidden;

            public function __construct()
            {
                $this->hidden = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
            }

            public function jsonSerialize(): string
            {
                return 'own';
            }
        };

        $data = (new BuildResponseData())(ResponseStatus::Success, (object) ['v' => $value], null, 'P');

        $this->assertSame($value, $data['v']);
        $this->assertStringContainsString('"v":"own"', (string) json_encode($data));
    }

    public function testDateInARejectContextStaysAsGiven(): void
    {
        $until = new \DateTimeImmutable('2026-10-17T12:00:00+00:00');

        $data = $this->rejectData(ResponseStatus::RuleViolation, 'order.locked', ['lockedUntil' => $until]);

        $this->assertSame($until, $data['context']['lockedUntil']);
    }
}
