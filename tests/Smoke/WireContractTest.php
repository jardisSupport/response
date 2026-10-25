<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Smoke;

use DateTimeImmutable;
use DateTimeZone;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\ContextResponse;
use JardisSupport\Response\Handler\Door\ValidateInput;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Failure\MapThrowable;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Handler\Response\TransformResponse;
use JardisSupport\Response\Tests\Support\EnvelopeCases;
use JardisSupport\Response\Tests\Support\EnvelopeJson;
use JardisSupport\Response\Tests\Support\Wire\QuoteInput;
use JardisSupport\Response\Tests\Support\Wire\QuoteLine;
use JardisSupport\Response\Tests\Support\Wire\QuoteValidation;
use JardisSupport\Scheduling\Clock\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * The wire contract of the package: the envelope bytes a door answers per status.
 *
 * `Contract/wire-contract.json` holds, for the ten cases 200, 200-empty (a last node without an answer of
 * its own: `data` carries only the `@type`), 200-merged (the answer is the data of all nodes united in run order,
 * the later node wins on the same field), 200-answer-class (merged with a process answer class: the union is
 * projected onto the declared fields, foreign data of the last node falls away), 200-datetime-offset (the answer holds an instant with a non-UTC offset,
 * the wire carries it in UTC), 400, 404, 409, 422 and 500, the envelope (status, data, errors, meta)
 * of the process handler. This test drives only the door steps - `ValidateInput`, `MapThrowable`,
 * `BuildFailureOutcome`, `BuildResponseData` and `TransformResponse` with a frozen clock - and compares the bytes
 * with the file. The proof that a generated handler reproduces the same file is the consumer test of the
 * builder (E4), not this one. The file ships with the package (the one `tests/` path that is not
 * `export-ignore`, see `.gitattributes`).
 *
 * Rewriting the file is explicit: run this test with the env switch UPDATE_WIRE_CONTRACT=1, e.g.
 * `docker compose run --rm --no-deps -e UPDATE_WIRE_CONTRACT=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php --filter WireContractTest /app/tests`.
 * A changed contract is a breaking change of the wire (major version).
 */
class WireContractTest extends TestCase
{
    private const FILE = __DIR__ . '/Contract/wire-contract.json';

    public function testWireContractFileHoldsEveryStatusAndMatchesTheDoorStepsByteForByte(): void
    {
        $document = $this->document();
        $bytes = EnvelopeJson::encode($document) . "\n";

        if (getenv('UPDATE_WIRE_CONTRACT') === '1') {
            file_put_contents(self::FILE, $bytes);
        }

        $this->assertFileExists(self::FILE);
        $this->assertSame($bytes, (string) file_get_contents(self::FILE), 'wire contract drifted - rewrite only with UPDATE_WIRE_CONTRACT=1');
    }

    public function testEveryCaseAnswersItsOwnStatus(): void
    {
        $cases = $this->document()['cases'];

        $expected = [
            ['200', 200],
            ['200-empty', 200],
            ['200-merged', 200],
            ['200-answer-class', 200],
            ['200-datetime-offset', 200],
            ['400', 400],
            ['404', 404],
            ['409', 409],
            ['422', 422],
            ['500', 500],
        ];

        $this->assertSame(array_column($expected, 0), array_map('strval', array_keys($cases)));
        foreach ($expected as [$key, $status]) {
            $this->assertSame($status, $cases[$key]['envelope']['status'], 'case ' . $key);
        }
        $this->assertSame(['@type' => 'voucherRedeemCheck'], $cases['200-empty']['envelope']['data']);
        $this->assertSame(
            ['@type' => 'orderQuoteSummary', 'currency' => 'EUR', 'lineCount' => 3, 'total' => '42.50'],
            $cases['200-merged']['envelope']['data']
        );
        $this->assertSame(
            ['@type' => 'orderShipment', 'orderNo' => 'A-1001', 'state' => 'Shipped'],
            $cases['200-answer-class']['envelope']['data']
        );
        $this->assertSame('2026-01-15T10:45:00+00:00', $cases['200-datetime-offset']['envelope']['data']['vouchers'][0]['createdAt']);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return [
            'contract' => 'jardissupport/workflow wire contract: the envelope per status that a generated process handler answers',
            'provenance' => [
                'source' => 'tests/Smoke/Fixture (copies of the generat, builder commit 165c9c030) run through the real runner',
                'clock' => 'frozen at 2026-01-15T10:30:00+00:00 (meta.duration 0.0)',
                'encoding' => 'EnvelopeJson::FLAGS, empty data/errors/meta as {}',
            ],
            'cases' => [
                '200' => $this->case('OrderBulkQuote', 'valid input, one action node answers a typed response', $this->success()),
                '200-empty' => $this->case('VoucherRedeemCheck', 'rule node is the last node and answers no data of its own, the answer is empty', $this->successWithoutAnswer()),
                '200-merged' => $this->case('OrderQuoteSummary', 'two action nodes, the answer is the data of both united in run order, the later node wins on the same field', $this->successMerged()),
                '200-answer-class' => $this->case('OrderShipment', 'merged with a process answer class: the last node is a sub-process with a foreign answer, only the declared fields reach the wire', $this->successAnswerClass()),
                '200-datetime-offset' => $this->case('VoucherOverview', 'last node answers an instant with the offset +02:00 in vouchers[].createdAt, the wire carries it in UTC', $this->successWithOffsetDate()),
                '400' => $this->case('OrderBulkQuote', 'start check: malformed input fields, no node runs', $this->invalidInput()),
                '404' => $this->case('RegisterAndReviseServiceOptOut', 'second call node: the callee answers 404, declared key', $this->revisionTargetMissing()),
                '409' => $this->case('RegisterAndReviseServiceOptOut', 'first call node: the callee answers 409, declared key', $this->registrationClash()),
                '422' => $this->case('VoucherRedeemCheck', 'rule node: the voucher has no root, rule violation', $this->ruleViolation()),
                '500' => $this->case('RegisterAndReviseServiceOptOut', 'first call node: the callee throws, empty data', $this->calleeDown()),
            ],
        ];
    }

    /**
     * @return array{process: string, scenario: string, envelope: array<string, mixed>}
     */
    private function case(string $process, string $scenario, DomainResponseInterface $response): array
    {
        return ['process' => $process, 'scenario' => $scenario, 'envelope' => EnvelopeJson::envelope($response)];
    }

    /**
     * The tail of a handler: data into the context response, the transformer with a frozen clock.
     *
     * @param array<string, mixed> $data
     * @param list<string> $errors
     */
    private function answer(
        string $process,
        ResponseStatus $status,
        array $data,
        array $errors = [],
        string $version = ''
    ): DomainResponseInterface {
        $result = new ContextResponse($process . 'Handler');
        foreach ($errors as $error) {
            $result->addError($error);
        }
        $result->setData($data);
        $clock = new FixedClock(new DateTimeImmutable(EnvelopeCases::FIXED_AT, new DateTimeZone('UTC')));

        return (new TransformResponse($clock, $version))($result, $status);
    }

    private function success(): DomainResponseInterface
    {
        $input = $this->quote('12.50', 'email', '3f2b8c1e-5d4a-4b7e-9c1d-0a1b2c3d4e5f');
        $this->assertSame([], (new ValidateInput(QuoteValidation::validator()))($input)->fields);
        $response = (object) ['lineCount' => 2, 'discountedLines' => 1, 'city' => 'Berlin', 'voucherCount' => 3];

        return $this->answer(
            'OrderBulkQuote',
            ResponseStatus::Success,
            (new BuildResponseData())(ResponseStatus::Success, $response, null, 'OrderBulkQuote'),
            [],
            'v1'
        );
    }

    private function successWithoutAnswer(): DomainResponseInterface
    {
        return $this->answer(
            'VoucherRedeemCheck',
            ResponseStatus::Success,
            (new BuildResponseData())(ResponseStatus::Success, new \stdClass(), null, 'VoucherRedeemCheck')
        );
    }

    /**
     * The answer form `merged` of the process engine: the data of all nodes united in run order, the later node
     * wins on the same field name (`array_replace`, `jardissupport/workflow` `BuildOutcome::merged`). The union is
     * written out here because this package does not depend on `workflow`; the byte proof against the real
     * engine is the consumer test of the builder.
     */
    private function successMerged(): DomainResponseInterface
    {
        $first = ['currency' => 'EUR', 'lineCount' => 2];
        $second = ['lineCount' => 3, 'total' => '42.50'];
        $response = (object) array_replace($first, $second);

        return $this->answer(
            'OrderQuoteSummary',
            ResponseStatus::Success,
            (new BuildResponseData())(ResponseStatus::Success, $response, null, 'OrderQuoteSummary')
        );
    }

    /**
     * The answer form `merged` with a process answer class (`jardissupport/workflow` `ProcessDefinition::answerResponse`):
     * the union of all steps (later wins) is projected onto the declared constructor fields of the answer class,
     * independent of kind and data of the last node. The last node here is a sub-process whose own answer carries
     * foreign fields (`carrier`, `trackingNo`); they fall away, the declared fields `orderNo` and `state` reach the
     * wire in declaration order. The projection is written out here because this package does not depend on
     * `workflow`; the byte proof against the real engine is the consumer test of the builder.
     */
    private function successAnswerClass(): DomainResponseInterface
    {
        $first = ['orderNo' => 'A-1001', 'state' => 'Packed'];
        $subProcessAnswer = ['state' => 'Shipped', 'carrier' => 'DHL', 'trackingNo' => 'T-77'];
        $united = array_replace($first, $subProcessAnswer);
        $declared = ['orderNo', 'state'];
        $response = (object) array_replace(array_flip($declared), array_intersect_key($united, array_flip($declared)));

        return $this->answer(
            'OrderShipment',
            ResponseStatus::Success,
            (new BuildResponseData())(ResponseStatus::Success, $response, null, 'OrderShipment')
        );
    }

    /**
     * The answer holds an instant 12:45+02:00. The data of `BuildResponseData` is not yet wire data; the
     * caller projects it before the transformer (the generated door: `data` ProjectWire with a type map, which
     * converts a `date-time` to UTC and formats it as ATOM). The projection is written out here as that
     * definition because this package does not depend on `data`; the byte proof against the real projection
     * is the consumer test of the builder.
     */
    private function successWithOffsetDate(): DomainResponseInterface
    {
        $response = (object) [
            'vouchers' => [[
                'voucherCode' => 'V-100',
                'amount' => '25.00',
                'note' => null,
                'issuedBy' => 'sales',
                'issuerId' => 'u-1',
                'createdAt' => new DateTimeImmutable('2026-01-15T12:45:00+02:00'),
            ]],
        ];
        $data = (new BuildResponseData())(ResponseStatus::Success, $response, null, 'VoucherOverview');
        $data['vouchers'][0]['createdAt'] = $data['vouchers'][0]['createdAt']
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);

        return $this->answer('VoucherOverview', ResponseStatus::Success, $data);
    }

    private function invalidInput(): DomainResponseInterface
    {
        $findings = (new ValidateInput(QuoteValidation::validator()))($this->quote('abc', 'fax', 'not-a-uuid'));
        $form = (new BuildFailureOutcome())(
            ResponseStatus::ValidationError,
            ValidateInput::MESSAGE_KEY,
            ['fields' => $findings->fields],
            $findings->errors
        );

        return $this->answer(
            'OrderBulkQuote',
            ResponseStatus::ValidationError,
            (new BuildResponseData())(ResponseStatus::ValidationError, $form, null, 'OrderBulkQuote'),
            $findings->errors
        );
    }

    private function ruleViolation(): DomainResponseInterface
    {
        return $this->reject('VoucherRedeemCheck', ResponseStatus::RuleViolation, 'sales.voucher.notFound', ['identifier' => 'unknown']);
    }

    private function registrationClash(): DomainResponseInterface
    {
        return $this->reject('RegisterAndReviseServiceOptOut', ResponseStatus::Conflict, 'registry.register_service_clash');
    }

    private function revisionTargetMissing(): DomainResponseInterface
    {
        return $this->reject('RegisterAndReviseServiceOptOut', ResponseStatus::NotFound, 'registry.revise_target_missing');
    }

    private function calleeDown(): DomainResponseInterface
    {
        $mapped = (new MapThrowable())(new \RuntimeException('callee down'), true, 'RegisterAndReviseServiceOptOut');

        return $this->answer(
            'RegisterAndReviseServiceOptOut',
            $mapped->status,
            (new BuildResponseData())($mapped->status, $mapped->response, null, 'RegisterAndReviseServiceOptOut')
        );
    }

    /**
     * A node rejects with a declared key: the failure form of the status, the key joins the data.
     *
     * @param array<string, mixed> $context
     */
    private function reject(string $process, ResponseStatus $status, string $key, array $context = []): DomainResponseInterface
    {
        $response = (new BuildFailureOutcome())($status, $key, $context, [], true);

        return $this->answer($process, $status, (new BuildResponseData())($status, $response, $key, $process));
    }

    private function quote(string $unitPrice, string $channel, string $serial): QuoteInput
    {
        return new QuoteInput([$channel], [new QuoteLine($unitPrice, [$serial]), new QuoteLine('3', null)]);
    }
}
