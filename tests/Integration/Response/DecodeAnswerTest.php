<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Response\Exception\NoJardisAnswerException;
use JardisSupport\Response\Handler\Response\DecodeAnswer;
use JardisSupport\Response\Tests\Support\EnvelopeJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The answer of another Jardis domain, body and status, comes back as a `DomainResponseInterface`.
 *
 * The invariant: envelope bytes -> `DecodeAnswer` -> response -> envelope bytes is byte-identical for every case of
 * the wire contract, in the form of the file (`EnvelopeJson::FLAGS`) and in the compact form a delivery layer sends
 * (`json_encode` without flags). The statuses 201, 401, 403 and 405 are read from the bytes of the counterpart
 * (`tests/Fixture/Counterpart`, origin in `ORIGIN.md`).
 */
class DecodeAnswerTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../Smoke/Contract/wire-contract.json';
    private const COUNTERPART = __DIR__ . '/../../Fixture/Counterpart/';

    /**
     * Row V3 "200 (five variants), 400, 404, 404 rule subject, 409, 422, 500": every case of the file in both forms.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function wireContractCases(): iterable
    {
        $document = json_decode((string) file_get_contents(self::CONTRACT), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $document);
        foreach (array_keys(get_object_vars($document->cases)) as $case) {
            foreach (['file', 'compact'] as $form) {
                yield $case . ' ' . $form => [(string) $case, $form];
            }
        }
    }

    #[DataProvider('wireContractCases')]
    public function testEveryCaseOfTheWireContractRoundTripsByteForByte(string $case, string $form): void
    {
        $document = json_decode((string) file_get_contents(self::CONTRACT), false, 512, JSON_THROW_ON_ERROR);
        $envelope = $document->cases->{$case}->envelope;
        $bytes = $this->write($envelope, $form);

        $response = (new DecodeAnswer())($bytes, $envelope->status);

        $this->assertSame($envelope->status, $response->getStatus());
        $this->assertSame([], $response->getEvents());
        $this->assertSame($bytes, $this->write(EnvelopeJson::envelope($response), $form));
    }

    public function testTheContractHoldsTheElevenCases(): void
    {
        $this->assertCount(22, iterator_to_array(self::wireContractCases()));
    }

    public function testNestedObjectsAreArraysAndOnlyAnEmptyObjectStaysAnObject(): void
    {
        $bytes = $this->bytesOf('404-rule-subject');

        $data = (new DecodeAnswer())($bytes, 404)->getData();

        $this->assertSame(['voucherCode' => 'unknown'], $data['context']);
        $empty = (new DecodeAnswer())($this->bytesOf('404'), 404)->getData();
        $this->assertInstanceOf(stdClass::class, $empty['context']);
        $this->assertSame([], get_object_vars($empty['context']));
    }

    public function testTheErrorsOfAListAnswerKeepTheirListForm(): void
    {
        $response = (new DecodeAnswer())($this->bytesOf('400'), 400);

        $this->assertSame(
            ['OrderBulkQuoteHandler' => ['Value is not in allowed list', 'Value is not in correct format', 'Invalid UUID format']],
            $response->getErrors()
        );
    }

    /**
     * Row V3 "201, 401, 403, 405": the bytes of the counterpart. 401 and 403 are excluded from byte equality, because
     * the delivery layer writes `errors` as texts and objects there; the reader normalises them to the contract type
     * (a list of texts per key) and the written-back bytes differ by exactly that. 405 has an empty `errors`: its bytes
     * happen to be identical, the exclusion is not needed there.
     *
     * @return iterable<string, array{int, string}>
     */
    public static function counterpartStatuses(): iterable
    {
        foreach ([201, 401, 403, 405] as $status) {
            yield (string) $status => [$status, self::COUNTERPART . $status . '.json'];
        }
    }

    #[DataProvider('counterpartStatuses')]
    public function testTheCounterpartAnswersCarryTheirStatus(int $status, string $file): void
    {
        $response = (new DecodeAnswer())((string) file_get_contents($file), $status);

        $this->assertSame($status, $response->getStatus());
        $this->assertSame([], $response->getEvents());
        $this->assertSame($status === 201, $response->isSuccess());
    }

    public function testCreatedIsWrittenBackByteForByte(): void
    {
        $bytes = (string) file_get_contents(self::COUNTERPART . '201.json');

        $response = (new DecodeAnswer())($bytes, 201);

        $this->assertSame('order', $response->getData()['@type']);
        $this->assertSame(1, $response->getData()['id']);
        $this->assertSame(['CreateOrder', 'PersistOrder'], $response->getMetadata()['contexts']);
        $this->assertSame($bytes, json_encode(EnvelopeJson::envelope($response)));
    }

    public function testUnauthorizedErrorsAreNormalisedToTheContractTypeAndAreExcludedFromByteEquality(): void
    {
        $bytes = (string) file_get_contents(self::COUNTERPART . '401.json');

        $response = (new DecodeAnswer())($bytes, 401);

        $this->assertSame(['message' => ['Authentication required.']], $response->getErrors());
        $this->assertSame([], $response->getData());
        $this->assertSame([], $response->getMetadata());
        $written = json_encode(EnvelopeJson::envelope($response));
        $this->assertNotSame($bytes, $written, 'excluded from byte equality: errors is normalised');
        $this->assertSame(
            '{"status":401,"data":{},"errors":{"message":["Authentication required."]},"meta":{}}',
            $written
        );
    }

    public function testForbiddenErrorsAreNormalisedToTheContractTypeAndAreExcludedFromByteEquality(): void
    {
        $bytes = (string) file_get_contents(self::COUNTERPART . '403.json');

        $response = (new DecodeAnswer())($bytes, 403);

        $this->assertSame(
            [
                'message' => ['Right "sales.order:read" is missing.'],
                'messageKey' => ['access.rightMissing'],
                'context' => ['{"right":"sales.order:read"}'],
            ],
            $response->getErrors()
        );
        $this->assertSame([], $response->getData());
        $written = json_encode(EnvelopeJson::envelope($response));
        $this->assertNotSame($bytes, $written, 'excluded from byte equality: errors is normalised');
        $this->assertSame(
            '{"status":403,"data":{},"errors":{"message":["Right \"sales.order:read\" is missing."],'
            . '"messageKey":["access.rightMissing"],"context":["{\"right\":\"sales.order:read\"}"]},"meta":{}}',
            $written
        );
    }

    public function testMethodNotAllowedHasNoErrorsAndNoData(): void
    {
        $bytes = (string) file_get_contents(self::COUNTERPART . '405.json');

        $response = (new DecodeAnswer())($bytes, 405);

        $this->assertSame([], $response->getData());
        $this->assertSame([], $response->getErrors());
        $this->assertSame([], $response->getMetadata());
        // the bytes happen to come back identical (empty errors); the exclusion of row V3 does not need it
        $this->assertSame($bytes, json_encode(EnvelopeJson::envelope($response)));
    }

    public function testNoContentWithoutABodyIsAnEmptyAnswerWithStatus204(): void
    {
        $response = (new DecodeAnswer())('', 204);

        $this->assertSame(204, $response->getStatus());
        $this->assertTrue($response->isSuccess());
        $this->assertSame([], $response->getData());
        $this->assertSame([], $response->getErrors());
        $this->assertSame([], $response->getMetadata());
        $this->assertSame([], $response->getEvents());
        $this->assertSame([], $response->getEvents(EventScope::Domain));
    }

    public function testNoContentWithABodyIsNoJardisAnswer(): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())('{"status":204,"data":{},"errors":{},"meta":{}}', 204);
    }

    public function testAListOfObjectsComesBackAsListOfArraysAndAnEmptyObjectStaysAnObject(): void
    {
        $bytes = '{"status":200,"data":{"@type":"voucherList","items":[{"id":1,"tags":["a","b"],"owner":{"name":"x"}},'
            . '{"id":2,"tags":[],"owner":{}},{}],"total":3},"errors":{},"meta":{"duration":0.25}}';

        $response = (new DecodeAnswer())($bytes, 200);

        $items = $response->getData()['items'];
        $this->assertSame(['id' => 1, 'tags' => ['a', 'b'], 'owner' => ['name' => 'x']], $items[0]);
        $this->assertSame(2, $items[1]['id']);
        $this->assertSame([], $items[1]['tags']);
        $this->assertInstanceOf(stdClass::class, $items[1]['owner']);
        $this->assertInstanceOf(stdClass::class, $items[2]);
        $this->assertSame(3, $response->getData()['total']);
        $this->assertSame($bytes, json_encode(EnvelopeJson::envelope($response)));
    }

    public function testErrorsAreFilledInTheContractType(): void
    {
        $bytes = '{"status":403,"data":{},"errors":{"text":"one","list":["a","b"],"number":7,"object":{"k":"v"},'
            . '"none":null},"meta":{}}';

        $errors = (new DecodeAnswer())($bytes, 403)->getErrors();

        $this->assertSame(
            [
                'text' => ['one'],
                'list' => ['a', 'b'],
                'number' => ['7'],
                'object' => ['{"k":"v"}'],
                'none' => ['null'],
            ],
            $errors
        );
    }

    /**
     * Row V3 "no JSON · JSON without the four keys · HTTP status differs from `status` · status outside
     * `ResponseStatus`": the named error of the package.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function notAJardisAnswer(): iterable
    {
        yield 'no JSON' => ['<html>Bad Gateway</html>', 200];
        yield 'empty body' => ['', 200];
        yield 'JSON null' => ['null', 200];
        yield 'JSON list' => ['[]', 200];
        yield 'JSON text' => ['"ok"', 200];
        yield 'without status' => ['{"data":{},"errors":{},"meta":{}}', 200];
        yield 'without data' => ['{"status":200,"errors":{},"meta":{}}', 200];
        yield 'without errors' => ['{"status":200,"data":{},"meta":{}}', 200];
        yield 'without meta' => ['{"status":200,"data":{},"errors":{}}', 200];
        yield 'data is a list' => ['{"status":200,"data":[],"errors":{},"meta":{}}', 200];
        yield 'errors is a text' => ['{"status":200,"data":{},"errors":"x","meta":{}}', 200];
        yield 'status is a text' => ['{"status":"200","data":{},"errors":{},"meta":{}}', 200];
        yield 'HTTP status differs from status' => ['{"status":404,"data":{},"errors":{},"meta":{}}', 200];
        yield 'HTTP 404 with envelope 200' => ['{"status":200,"data":{},"errors":{},"meta":{}}', 404];
        yield 'gateway 502' => ['{"status":502,"data":{},"errors":{},"meta":{}}', 502];
        yield 'gateway 502 with a page' => ['<html>Bad Gateway</html>', 502];
        yield 'redirect 302' => ['{"status":302,"data":{},"errors":{},"meta":{}}', 302];
        yield 'status 0' => ['{"status":0,"data":{},"errors":{},"meta":{}}', 0];
    }

    #[DataProvider('notAJardisAnswer')]
    public function testWhatIsNoJardisAnswerIsNamed(string $body, int $status): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())($body, $status);
    }

    public function testAnErrorEntryThatHasNoTextFormIsNoJardisAnswer(): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())('{"status":403,"data":{},"errors":{"number":1e999},"meta":{}}', 403);
    }

    public function testAnUnreadableBodyKeepsItsCause(): void
    {
        try {
            (new DecodeAnswer())('{"status":', 200);
            $this->fail('NoJardisAnswerException expected');
        } catch (NoJardisAnswerException $exception) {
            $this->assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    private function bytesOf(string $case): string
    {
        $document = json_decode((string) file_get_contents(self::CONTRACT), false, 512, JSON_THROW_ON_ERROR);

        return EnvelopeJson::encode($document->cases->{$case}->envelope);
    }

    /**
     * The bytes of an envelope in the form of the file or in the compact form of a delivery layer.
     *
     * @param array<string, mixed>|stdClass $envelope
     */
    private function write(array|stdClass $envelope, string $form): string
    {
        return $form === 'file' ? EnvelopeJson::encode($envelope) : json_encode($envelope, JSON_THROW_ON_ERROR);
    }
}
