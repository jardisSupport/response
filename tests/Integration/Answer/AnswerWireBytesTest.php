<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Answer;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\InputFindings;
use JardisSupport\Response\Data\Response\ContextResponse;
use JardisSupport\Response\Data\RuleResult;
use JardisSupport\Response\Handler\Answer\AnswerFindings;
use JardisSupport\Response\Handler\Answer\AnswerNotFound;
use JardisSupport\Response\Handler\Answer\AnswerOutcome;
use JardisSupport\Response\Handler\Answer\AnswerRuleViolation;
use JardisSupport\Response\Handler\Answer\AnswerThrowable;
use JardisSupport\Response\Handler\Answer\AnswerUnauthorized;
use JardisSupport\Response\Handler\Door\ValidateInput;
use JardisSupport\Response\Tests\Support\EnvelopeJson;
use JardisSupport\Response\Tests\Support\Outcome\AnswerHarness;
use JardisSupport\Response\Tests\Support\Outcome\FakeOutcome;
use JardisSupport\Response\Tests\Support\Wire\QuoteInput;
use JardisSupport\Response\Tests\Support\Wire\QuoteLine;
use JardisSupport\Response\Tests\Support\Wire\QuoteValidation;
use PHPUnit\Framework\TestCase;

/**
 * Bytes parity of the answer closures with the montage a generated door writes today.
 *
 * The reference is the shipped wire contract (`Smoke/Contract/wire-contract.json`, one envelope per status of the
 * process door) and the frozen envelope fixtures (`Fixture/envelope/*.json`, recorded from the generat): each
 * closure must produce exactly the bytes the former hand-assembled sequence produced.
 */
class AnswerWireBytesTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../Smoke/Contract/wire-contract.json';

    public function testAnswerFindingsReproducesTheWire400(): void
    {
        $findings = (new ValidateInput(QuoteValidation::validator()))(
            new QuoteInput(['fax'], [new QuoteLine('abc', ['not-a-uuid']), new QuoteLine('3', null)])
        );
        $this->assertTrue($findings->failed());

        $answer = (new AnswerFindings())(AnswerHarness::result('OrderBulkQuoteHandler'), AnswerHarness::transform(), $findings);

        $this->assertWire('400', $answer);
    }

    public function testAnswerUnauthorizedReproducesTheWire401(): void
    {
        $answer = (new AnswerUnauthorized())(AnswerHarness::result('VoucherRedeemCheckHandler'), AnswerHarness::transform());

        $this->assertWire('401', $answer);
    }

    public function testAnswerOutcomeReproducesTheDeclaredRejectsOfTheWire(): void
    {
        $notFound = new FakeOutcome(ResponseStatus::NotFound, messageKey: 'registry.revise_target_missing');
        $conflict = new FakeOutcome(ResponseStatus::Conflict, messageKey: 'registry.register_service_clash');
        $rule = new FakeOutcome(
            ResponseStatus::RuleViolation,
            messageKey: 'sales.voucher.notFound',
            context: ['identifier' => 'unknown']
        );
        $registry = 'RegisterAndReviseServiceOptOutHandler';

        $this->assertWire('404', $this->outcome($registry, $notFound, 'RegisterAndReviseServiceOptOut'));
        $this->assertWire('409', $this->outcome($registry, $conflict, 'RegisterAndReviseServiceOptOut'));
        $this->assertWire('422', $this->outcome('VoucherRedeemCheckHandler', $rule, 'VoucherRedeemCheck'));
    }

    public function testAnswerOutcomeReproducesTheSuccessesOfTheWire(): void
    {
        $quote = new FakeOutcome(
            ResponseStatus::Success,
            (object) ['lineCount' => 2, 'discountedLines' => 1, 'city' => 'Berlin', 'voucherCount' => 3]
        );
        $empty = new FakeOutcome(ResponseStatus::Success);
        $merged = new FakeOutcome(
            ResponseStatus::Success,
            (object) ['currency' => 'EUR', 'lineCount' => 3, 'total' => '42.50']
        );
        $shipment = new FakeOutcome(ResponseStatus::Success, (object) ['orderNo' => 'A-1001', 'state' => 'Shipped']);

        $this->assertWire('200', $this->outcome('OrderBulkQuoteHandler', $quote, 'OrderBulkQuote', 'v1'));
        $this->assertWire('200-empty', $this->outcome('VoucherRedeemCheckHandler', $empty, 'VoucherRedeemCheck'));
        $this->assertWire('200-merged', $this->outcome('OrderQuoteSummaryHandler', $merged, 'OrderQuoteSummary'));
        $this->assertWire('200-answer-class', $this->outcome('OrderShipmentHandler', $shipment, 'OrderShipment'));
    }

    public function testAnswerThrowableReproducesTheWire500(): void
    {
        $answer = (new AnswerThrowable())(
            AnswerHarness::result('RegisterAndReviseServiceOptOutHandler'),
            AnswerHarness::transform(),
            new \RuntimeException('callee down'),
            true,
            'RegisterAndReviseServiceOptOut'
        );

        $this->assertWire('500', $answer);
    }

    public function testAnswerFindingsReproducesTheRecordedValidationEnvelope(): void
    {
        $fixture = $this->fixture('validation');
        /** @var list<array{field: string, reason: string, message: string, messageKey: string}> $fields */
        $fields = json_decode(EnvelopeJson::encode($fixture->body->data->fields), true);
        $result = AnswerHarness::result('order');
        $result->addResult((new ContextResponse('validator'))->addError('items[1].qty must be at least 1'));
        $findings = new InputFindings($fields, ['email is required']);

        $answer = (new AnswerFindings())($result, AnswerHarness::transform(), $findings);

        $this->assertSame(EnvelopeJson::encode($fixture->body), AnswerHarness::bytes($answer));
    }

    public function testAnswerNotFoundReproducesTheRecordedEnvelope(): void
    {
        $fixture = $this->fixture('notFound');

        $answer = (new AnswerNotFound())(
            AnswerHarness::result('order'),
            AnswerHarness::transform(),
            ['orderNumber' => 'ORD-404']
        );

        $this->assertSame(EnvelopeJson::encode($fixture->body), AnswerHarness::bytes($answer));
    }

    public function testAnswerRuleViolationReproducesTheRecordedEnvelopeWithAnEmptyContextAsObject(): void
    {
        $fixture = $this->fixture('ruleViolation');

        $answer = (new AnswerRuleViolation())(
            AnswerHarness::result('order'),
            AnswerHarness::transform('v2'),
            RuleResult::reject('OrderMustBeOpen', 'order.rule.open')
        );

        $this->assertSame(EnvelopeJson::encode($fixture->body), AnswerHarness::bytes($answer));
        $this->assertStringContainsString('"context": {}', AnswerHarness::bytes($answer));
    }

    private function outcome(
        string $context,
        FakeOutcome $outcome,
        string $process,
        string $version = ''
    ): DomainResponseInterface {
        return (new AnswerOutcome())(AnswerHarness::result($context), AnswerHarness::transform($version), $outcome, $process);
    }

    private function assertWire(string $case, DomainResponseInterface $answer): void
    {
        $contract = json_decode((string) file_get_contents(self::CONTRACT), false, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            EnvelopeJson::encode($contract->cases->{$case}->envelope),
            AnswerHarness::bytes($answer),
            'wire case ' . $case
        );
    }

    private function fixture(string $name): \stdClass
    {
        $path = dirname(__DIR__, 2) . '/Fixture/envelope/' . $name . '.json';

        /** @var \stdClass */
        return json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    }
}
