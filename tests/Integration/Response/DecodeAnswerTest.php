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

    /**
     * A number without a JSON form is no value a Jardis domain writes: at any depth of `data` and `meta`, positive or
     * negative, it is no answer - writing it back would fail with a foreign `JsonException`.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function numbersWithoutJsonForm(): iterable
    {
        yield 'INF in data' => ['{"status":200,"data":{"n":1e999},"errors":{},"meta":{}}', 200];
        yield '-INF in data' => ['{"status":200,"data":{"n":-1e999},"errors":{},"meta":{}}', 200];
        yield 'INF nested in data' => ['{"status":200,"data":{"a":{"b":[1,{"c":1e999}]}},"errors":{},"meta":{}}', 200];
        yield 'INF in a list of data' => ['{"status":200,"data":{"items":[[1e999]]},"errors":{},"meta":{}}', 200];
        yield 'INF in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"duration":1e999}}', 200];
        yield '-INF nested in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"a":{"b":-1e999}}}', 200];
        yield 'INF in data of a failure' => ['{"status":404,"data":{"n":1e999},"errors":{},"meta":{}}', 404];
        yield 'INF in errors' => ['{"status":403,"data":{},"errors":{"n":[1e999]},"meta":{}}', 403];
        yield 'INF nested in errors' => ['{"status":403,"data":{},"errors":{"n":{"a":-1e999}},"meta":{}}', 403];
    }

    #[DataProvider('numbersWithoutJsonForm')]
    public function testANumberWithoutJsonFormIsNoJardisAnswer(string $body, int $status): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())($body, $status);
    }

    public function testAKeyOfErrorsThatIsNoTextIsNoJardisAnswer(): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())('{"status":400,"data":{},"errors":{"0":"x"},"meta":{}}', 400);
    }

    /**
     * One shape rule at every depth: a non-empty object whose keys are `0`, `1`, ... without a gap would be written as a
     * JSON list, so it is no answer - on top of `data`/`meta` and nested, in objects and in lists.
     */
    #[DataProvider('partsWithTheKeysOfAList')]
    public function testAnObjectWithTheKeysOfAListIsNoJardisAnswerAtEveryDepth(string $body, int $status): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())($body, $status);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function partsWithTheKeysOfAList(): iterable
    {
        yield 'data' => ['{"status":200,"data":{"0":"a","1":"b"},"errors":{},"meta":{}}', 200];
        yield 'meta' => ['{"status":200,"data":{},"errors":{},"meta":{"0":"a"}}', 200];
        yield 'nested in data' => ['{"status":200,"data":{"x":{"0":"a","1":"b"}},"errors":{},"meta":{}}', 200];
        yield 'nested in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"x":{"0":"a","1":"b"}}}', 200];
        yield 'one key nested in data' => ['{"status":200,"data":{"x":{"0":"a"}},"errors":{},"meta":{}}', 200];
        yield 'in a list of data' => ['{"status":200,"data":{"x":[{"0":"a","1":"b"}]},"errors":{},"meta":{}}', 200];
        yield 'in a list of a list in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"x":[[{"0":"a"}]]}}', 200];
        yield 'nested in data of a failure' => ['{"status":404,"data":{"x":{"0":"a","1":"b"}},"errors":{},"meta":{}}', 404];
        yield 'deep in data' => ['{"status":200,"data":{"a":{"b":{"c":[{"d":{"0":1,"1":2,"2":3}}]}}},"errors":{},"meta":{}}', 200];
        yield 'nested object in a list-like object' => ['{"status":200,"data":{"x":{"0":{"y":1},"1":{"0":"z"}}},"errors":{},"meta":{}}', 200];
    }

    /**
     * The counter-proof of the rule: what stays an object (name beside the numbers, gap, other order, an empty object)
     * and what stays a list (a real list, an empty list) is an answer and is written back with the same form, at the top
     * and nested, byte for byte.
     *
     * @return iterable<string, array{string}>
     */
    public static function shapesThatSurviveTheRoundTrip(): iterable
    {
        yield 'real list in data' => ['{"status":200,"data":{"x":["a","b"]},"errors":{},"meta":{}}'];
        yield 'real list of objects in data' => ['{"status":200,"data":{"x":[{"a":1},{"b":2}]},"errors":{},"meta":{}}'];
        yield 'empty list nested in data' => ['{"status":200,"data":{"x":[]},"errors":{},"meta":{}}'];
        yield 'empty object nested in data' => ['{"status":200,"data":{"x":{}},"errors":{},"meta":{}}'];
        yield 'empty list and empty object nested in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"l":[],"o":{}}}'];
        yield 'empty object and list in a list' => ['{"status":200,"data":{"x":[{},[]]},"errors":{},"meta":{}}'];
        yield 'numeric keys with a gap nested' => ['{"status":200,"data":{"x":{"1":"a","3":"b"}},"errors":{},"meta":{}}'];
        yield 'numeric key with a name nested' => ['{"status":200,"data":{"x":{"0":"a","y":"b"}},"errors":{},"meta":{}}'];
        yield 'numeric keys in another order nested' => ['{"status":200,"data":{"x":{"1":"a","0":"b"}},"errors":{},"meta":{}}'];
        yield 'numeric keys not starting at zero nested in a list' => ['{"status":200,"data":{"x":[{"1":"a"}]},"errors":{},"meta":{}}'];
        yield 'numeric keys at the top of meta with a gap' => ['{"status":200,"data":{},"errors":{},"meta":{"2":"a","5":"b"}}'];
        yield 'key with a NUL byte inside' => ['{"status":200,"data":{"a\u0000b":1,"c\u0000":{"d\u0000e":2}},"errors":{},"meta":{}}'];
    }

    #[DataProvider('shapesThatSurviveTheRoundTrip')]
    public function testShapesThatAreNotAListStayWhatTheyWereAtEveryDepth(string $body): void
    {
        $response = (new DecodeAnswer())($body, 200);

        $this->assertSame($body, json_encode(EnvelopeJson::envelope($response)));
    }

    public function testAnEmptyObjectStaysAnObjectAndAnEmptyListStaysAListWhileTheTopOfAPartMustBeAnObject(): void
    {
        $nested = (new DecodeAnswer())('{"status":200,"data":{"o":{},"l":[]},"errors":{},"meta":{}}', 200)->getData();

        $this->assertInstanceOf(stdClass::class, $nested['o']);
        $this->assertSame([], $nested['l']);

        $top = (new DecodeAnswer())('{"status":200,"data":{},"errors":{},"meta":{}}', 200);
        $this->assertSame([], $top->getData());
        $this->assertSame('{"status":200,"data":{},"errors":{},"meta":{}}', json_encode(EnvelopeJson::envelope($top)));

        $this->expectException(NoJardisAnswerException::class);
        (new DecodeAnswer())('{"status":200,"data":[],"errors":{},"meta":{}}', 200);
    }

    /**
     * A key that starts with a NUL byte is refused by PHP when it decodes into an object (mangled property names); it is
     * no answer at any depth - the envelope, `data`, `meta`, `errors`, nested and in lists.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function keysWithALeadingNulByte(): iterable
    {
        yield 'envelope' => ['{"status":200,"data":{},"errors":{},"meta":{},"\u0000x":1}', 200];
        yield 'data' => ['{"status":200,"data":{"\u0000x":1},"errors":{},"meta":{}}', 200];
        yield 'meta' => ['{"status":200,"data":{},"errors":{},"meta":{"\u0000x":1}}', 200];
        yield 'errors' => ['{"status":400,"data":{},"errors":{"\u0000x":["a"]},"meta":{}}', 400];
        yield 'nested in data' => ['{"status":200,"data":{"a":{"\u0000x":1}},"errors":{},"meta":{}}', 200];
        yield 'nested in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"a":{"b":{"\u0000x":1}}}}', 200];
        yield 'in a list of data' => ['{"status":200,"data":{"a":[{"\u0000x":1}]},"errors":{},"meta":{}}', 200];
        yield 'nested in errors' => ['{"status":400,"data":{},"errors":{"a":[{"\u0000x":1}]},"meta":{}}', 400];
        yield 'only the NUL byte' => ['{"status":200,"data":{"\u0000":1},"errors":{},"meta":{}}', 200];
    }

    #[DataProvider('keysWithALeadingNulByte')]
    public function testAKeyWithALeadingNulByteIsNoJardisAnswerAtEveryDepth(string $body, int $status): void
    {
        try {
            (new DecodeAnswer())($body, $status);
            $this->fail('A key with a leading NUL byte must be refused.');
        } catch (NoJardisAnswerException $refused) {
            $this->assertStringContainsString('NUL byte', $refused->getMessage());
            $this->assertInstanceOf(\JsonException::class, $refused->getPrevious());
        }
    }

    public function testNumericKeysThatAreNotAListStayAnswers(): void
    {
        $bytes = '{"status":200,"data":{"0":"a","x":1},"errors":{},"meta":{"1":"a","3":"b"}}';

        $response = (new DecodeAnswer())($bytes, 200);

        $this->assertSame($bytes, json_encode(EnvelopeJson::envelope($response)));
    }

    public function testTheLargestNumbersAreStillAnswers(): void
    {
        $bytes = '{"status":200,"data":{"big":1.7976931348623157e308,"small":-1.7976931348623157e308,'
            . '"tiny":5e-324,"over":1e19,"int":9223372036854775807},"errors":{},"meta":{}}';

        $response = (new DecodeAnswer())($bytes, 200);

        $this->assertIsFloat($response->getData()['big']);
        $this->assertSame(PHP_INT_MAX, $response->getData()['int']);
        $this->assertNotSame('', json_encode(EnvelopeJson::envelope($response), JSON_THROW_ON_ERROR));
    }

    /**
     * The invariant of the class: what `DecodeAnswer` returns without `NoJardisAnswerException` can be written into the
     * envelope again - in the compact form of a delivery layer and in the form of the file - without any exception.
     * Cases: every case of the wire contract, the counterpart, the cases above and hostile values (nesting at and over
     * the depth limit, numeric keys, odd texts, empty and huge parts).
     *
     * @return iterable<string, array{string, int}>
     */
    public static function everyBodyThatIsAnswered(): iterable
    {
        $document = json_decode((string) file_get_contents(self::CONTRACT), false, 512, JSON_THROW_ON_ERROR);
        foreach ($document->cases as $case => $entry) {
            yield 'contract ' . $case => [EnvelopeJson::encode($entry->envelope), $entry->envelope->status];
        }
        foreach ([201, 401, 403, 405] as $status) {
            yield 'counterpart ' . $status => [(string) file_get_contents(self::COUNTERPART . $status . '.json'), $status];
        }
        foreach (self::numbersWithoutJsonForm() as $name => $case) {
            yield 'rejected ' . $name => $case;
        }
        yield 'rejected errors numeric key' => ['{"status":400,"data":{},"errors":{"0":"x"},"meta":{}}', 400];
        yield 'numeric keys in data' => ['{"status":200,"data":{"0":"a","1":"b","x":{"5":1,"7":2}},"errors":{},"meta":{}}', 200];
        foreach (self::partsWithTheKeysOfAList() as $name => $case) {
            yield 'rejected list-like ' . $name => $case;
        }
        foreach (self::keysWithALeadingNulByte() as $name => $case) {
            yield 'rejected NUL key ' . $name => $case;
        }
        foreach (self::integersBeyondInt64() as $name => $case) {
            yield 'rejected integer ' . $name => $case;
        }
        foreach (self::objectsWithAKeyTwice() as $name => $case) {
            yield 'rejected key twice ' . $name => $case;
        }
        foreach (self::keysThatAreNotTwice() as $name => [$body]) {
            yield 'key ' . $name => [$body, 200];
        }
        foreach (self::shapesThatSurviveTheRoundTrip() as $name => [$body]) {
            yield 'shape ' . $name => [$body, 200];
        }
        yield 'numeric key with a name in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"0":"a","x":1}}', 200];
        yield 'numeric keys with a gap in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"1":"a","3":"b"}}', 200];
        yield 'empty and odd keys' => ['{"status":200,"data":{"":1," ":2,"a.b":3,"\u00e4":4},"errors":{"":["x"]},"meta":{}}', 200];
        yield 'texts' => ['{"status":200,"data":{"t":"\u00e4\ud83d\ude00\/\u0000\n"},"errors":{},"meta":{}}', 200];
        yield 'numbers' => ['{"status":200,"data":{"a":-0,"b":-0.0,"c":0.1e1,"d":1E2,"e":1.2345678901234568e29},"errors":{},"meta":{}}', 200];
        yield 'errors without a text form but finite' => ['{"status":500,"data":{},"errors":{"a":[true,null,1.5,{"k":[]}]},"meta":{}}', 500];
        yield 'errors with an empty list' => ['{"status":400,"data":{},"errors":{"a":[]},"meta":{}}', 400];
        yield 'nesting at the limit of data' => [self::nested(509), 200];
        yield 'nesting at the limit of the decoder' => [self::nested(510), 200];
        yield 'nesting over the limit' => [self::nested(511), 200];
        yield 'nesting of objects at the limit' => [self::nestedObjects(509), 200];
        yield 'nesting of objects over the limit' => [self::nestedObjects(511), 200];
        yield 'nesting in meta at the limit' => [self::nested(509, 'meta'), 200];
        yield 'nesting in errors at the limit' => [self::nested(509, 'errors'), 400];
        yield 'many members' => [
            '{"status":200,"data":{' . implode(',', array_map(static fn (int $i): string => '"k' . $i . '":' . $i, range(1, 2000)))
            . '},"errors":{},"meta":{}}',
            200,
        ];
    }

    #[DataProvider('everyBodyThatIsAnswered')]
    public function testWhatComesBackWithoutAnExceptionIsWrittenBackWithoutAnException(string $body, int $status): void
    {
        try {
            $response = (new DecodeAnswer())($body, $status);
        } catch (NoJardisAnswerException) {
            $this->addToAssertionCount(1);

            return;
        }

        $compact = json_encode(EnvelopeJson::envelope($response), JSON_THROW_ON_ERROR);
        $file = EnvelopeJson::encode(EnvelopeJson::envelope($response));
        $this->assertNotSame('', $compact);
        $this->assertNotSame('', $file);
        // and the written bytes are an answer again
        $again = (new DecodeAnswer())($compact, $status);
        $this->assertSame($status, $again->getStatus());
    }

    /**
     * An integer beyond 64 bits would be read as a float by the decoder: another value (rounded) and another type, so
     * it is no answer - at any depth of the envelope, `data`, `meta` and `errors`, in objects and in lists, positive
     * and negative, in a failure answer as well.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function integersBeyondInt64(): iterable
    {
        $big = '99999999999999999999';
        yield 'positive in data' => ['{"status":200,"data":{"n":' . $big . '},"errors":{},"meta":{}}', 200];
        yield 'negative in data' => ['{"status":200,"data":{"n":-9223372036854775809},"errors":{},"meta":{}}', 200];
        yield 'PHP_INT_MAX plus one' => ['{"status":200,"data":{"n":9223372036854775808},"errors":{},"meta":{}}', 200];
        yield 'twenty digits with a leading minus' => ['{"status":200,"data":{"n":-' . $big . '},"errors":{},"meta":{}}', 200];
        yield 'thirty digits' => ['{"status":200,"data":{"n":123456789012345678901234567890},"errors":{},"meta":{}}', 200];
        yield 'in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"n":' . $big . '}}', 200];
        yield 'nested in data' => ['{"status":200,"data":{"a":{"b":[1,{"c":' . $big . '}]}},"errors":{},"meta":{}}', 200];
        yield 'nested in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"a":{"b":-' . $big . '}}}', 200];
        yield 'in a list of a list in data' => ['{"status":200,"data":{"x":[[' . $big . ']]},"errors":{},"meta":{}}', 200];
        yield 'in data of a failure' => ['{"status":404,"data":{"n":' . $big . '},"errors":{},"meta":{}}', 404];
        yield 'in errors' => ['{"status":403,"data":{},"errors":{"n":[' . $big . ']},"meta":{}}', 403];
        yield 'nested in errors' => ['{"status":403,"data":{},"errors":{"n":{"a":' . $big . '}},"meta":{}}', 403];
        yield 'in a key beside the four' => ['{"status":200,"data":{},"errors":{},"meta":{},"x":' . $big . '}', 200];
        yield 'after a string with an escaped quote' => ['{"status":200,"data":{"s":"a\"b\\\\","n":' . $big . '},"errors":{},"meta":{}}', 200];
        yield 'pretty printed' => ["{\n  \"status\": 200,\n  \"data\": {\n    \"n\": " . $big . "\n  },\n  \"errors\": {},\n  \"meta\": {}\n}", 200];
        yield 'last token of a list' => ['{"status":200,"data":{"x":[1,' . $big . ']},"errors":{},"meta":{}}', 200];
    }

    #[DataProvider('integersBeyondInt64')]
    public function testAnIntegerBeyondInt64IsNoJardisAnswerAtEveryDepth(string $body, int $status): void
    {
        try {
            (new DecodeAnswer())($body, $status);
            $this->fail('An integer beyond 64 bits must be refused.');
        } catch (NoJardisAnswerException $refused) {
            $this->assertStringContainsString('beyond 64 bits', $refused->getMessage());
        }
    }

    /**
     * The counter-proof: the numbers at the edge and the numbers that are floats in the text keep value and type, and
     * what looks like a number inside a string is a string.
     */
    public function testNumbersAtTheEdgeOfInt64AndFloatsInTheTextKeepValueAndType(): void
    {
        $bytes = '{"status":200,"data":{"max":9223372036854775807,"min":-9223372036854775808,"zero":0,"one":1.0,'
            . '"half":1.5,"thousand":1000.0,"over":1.0e+19,"nested":{"l":[9223372036854775807,-9223372036854775808,2.5]},'
            . '"text":"99999999999999999999","key99999999999999999999":"-9223372036854775809"},'
            . '"errors":{},"meta":{"max":9223372036854775807,"min":-9223372036854775808}}';

        $response = (new DecodeAnswer())($bytes, 200);

        $data = $response->getData();
        $this->assertSame(PHP_INT_MAX, $data['max']);
        $this->assertSame(PHP_INT_MIN, $data['min']);
        $this->assertSame(0, $data['zero']);
        $this->assertSame(1.0, $data['one']);
        $this->assertSame(1.5, $data['half']);
        $this->assertSame(1000.0, $data['thousand']);
        $this->assertSame(1.0e19, $data['over']);
        $this->assertSame([PHP_INT_MAX, PHP_INT_MIN, 2.5], $data['nested']['l']);
        $this->assertSame('99999999999999999999', $data['text']);
        $this->assertSame('-9223372036854775809', $data['key99999999999999999999']);
        $this->assertSame(PHP_INT_MIN, $response->getMetadata()['min']);
        $this->assertSame($bytes, json_encode(EnvelopeJson::envelope($response), JSON_PRESERVE_ZERO_FRACTION));
    }

    public function testTheTextFormOfAFloatInTheBodyDoesNotMatter(): void
    {
        $response = (new DecodeAnswer())(
            '{"status":200,"data":{"a":1e3,"b":1E19,"c":0.1e1,"d":-0.0,"e":1.2345678901234568e29,"f":1.0},"errors":{},"meta":{}}',
            200
        );

        $this->assertSame([1000.0, 1.0e19, 1.0, -0.0, 1.2345678901234568e29, 1.0], array_values($response->getData()));
    }

    /**
     * A key that occurs twice in one object keeps only its last value in the decoder - data lost without a trace - so it
     * is no answer: on top and nested, in `data`, `meta`, `errors`, the envelope and in lists, with the same value or
     * another, written the same way or with an escape.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function objectsWithAKeyTwice(): iterable
    {
        yield 'in data' => ['{"status":200,"data":{"a":1,"a":2},"errors":{},"meta":{}}', 200];
        yield 'in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"a":1,"a":2}}', 200];
        yield 'in errors' => ['{"status":400,"data":{},"errors":{"a":["x"],"a":["y"]},"meta":{}}', 400];
        yield 'in the envelope' => ['{"status":200,"status":200,"data":{},"errors":{},"meta":{}}', 200];
        yield 'a part twice' => ['{"status":200,"data":{"a":1},"errors":{},"meta":{},"data":{"a":2}}', 200];
        yield 'beside the four' => ['{"status":200,"data":{},"errors":{},"meta":{},"x":1,"x":2}', 200];
        yield 'nested in data' => ['{"status":200,"data":{"x":{"a":1,"a":2}},"errors":{},"meta":{}}', 200];
        yield 'nested in meta' => ['{"status":200,"data":{},"errors":{},"meta":{"x":{"y":{"a":1,"a":2}}}}', 200];
        yield 'in a list of data' => ['{"status":200,"data":{"x":[{"a":1},{"b":1,"b":1}]},"errors":{},"meta":{}}', 200];
        yield 'nested in errors' => ['{"status":400,"data":{},"errors":{"x":[{"a":1,"a":2}]},"meta":{}}', 400];
        yield 'in data of a failure' => ['{"status":404,"data":{"a":1,"a":2},"errors":{},"meta":{}}', 404];
        yield 'with the same value' => ['{"status":200,"data":{"a":1,"a":1},"errors":{},"meta":{}}', 200];
        yield 'with an escape' => ['{"status":200,"data":{"a":1,"a":2},"errors":{},"meta":{}}', 200];
        yield 'with an escaped slash' => ['{"status":200,"data":{"a/b":1,"a\/b":2},"errors":{},"meta":{}}', 200];
        yield 'empty key' => ['{"status":200,"data":{"":1,"":2},"errors":{},"meta":{}}', 200];
        yield 'numeric key' => ['{"status":200,"data":{"0":1,"x":0,"0":2},"errors":{},"meta":{}}', 200];
        yield 'not neighbours' => ['{"status":200,"data":{"a":1,"b":2,"c":3,"a":4},"errors":{},"meta":{}}', 200];
        yield 'after a closed object' => ['{"status":200,"data":{"a":{"b":1},"a":2},"errors":{},"meta":{}}', 200];
        yield 'after a closed list' => ['{"status":200,"data":{"a":[1,[2]],"a":2},"errors":{},"meta":{}}', 200];
        yield 'after a string with an escaped backslash' => ['{"status":200,"data":{"p":"\\\\","a":1,"a":2},"errors":{},"meta":{}}', 200];
        yield 'after a string with an escaped quote' => ['{"status":200,"data":{"p":"x\"","a":1,"a":2},"errors":{},"meta":{}}', 200];
        yield 'after a string with structure marks' => ['{"status":200,"data":{"p":"{[,:]}","a":1,"a":2},"errors":{},"meta":{}}', 200];
        yield 'pretty printed' => ["{\n  \"status\": 200,\n  \"data\": {\n    \"a\": 1,\n    \"a\": 2\n  },\n  \"errors\": {},\n  \"meta\": {}\n}", 200];
        yield 'deep' => ['{"status":200,"data":{"a":{"b":{"c":[{"d":{"e":1,"f":2,"e":3}}]}}},"errors":{},"meta":{}}', 200];
    }

    #[DataProvider('objectsWithAKeyTwice')]
    public function testAnObjectWithAKeyTwiceIsNoJardisAnswerAtEveryDepth(string $body, int $status): void
    {
        try {
            (new DecodeAnswer())($body, $status);
            $this->fail('A key twice in one object must be refused.');
        } catch (NoJardisAnswerException $refused) {
            $this->assertStringContainsString('key twice', $refused->getMessage());
        }
    }

    /**
     * The counter-proof: the same key in different objects, keys that differ in case or in escape-free bytes, and
     * texts that look like keys or numbers inside a string value are answers and come back unchanged.
     *
     * @return iterable<string, array{string}>
     */
    public static function keysThatAreNotTwice(): iterable
    {
        yield 'case differs' => ['{"status":200,"data":{"a":1,"A":2},"errors":{},"meta":{}}'];
        yield 'same key in sibling objects' => ['{"status":200,"data":{"x":{"a":1},"y":{"a":2},"a":3},"errors":{},"meta":{"a":4}}'];
        yield 'same key in the elements of a list' => ['{"status":200,"data":{"x":[{"a":1},{"a":2},{"a":3}]},"errors":{},"meta":{}}'];
        yield 'same key in a parent and its child' => ['{"status":200,"data":{"a":{"a":{"a":1}}},"errors":{},"meta":{}}'];
        yield 'same key in data and meta' => ['{"status":200,"data":{"a":1},"errors":{},"meta":{"a":1}}'];
        yield 'keys that look alike' => ['{"status":200,"data":{"a":1,"a ":2," a":3,"a\u0000":4,"":5," ":6},"errors":{},"meta":{}}'];
        yield 'numeric keys that differ' => ['{"status":200,"data":{"0":1,"00":2,"x":3,"-0":4,"0.0":5},"errors":{},"meta":{}}'];
        yield 'keys inside a string value' => ['{"status":200,"data":{"x":"\"a\":1,\"a\":2"},"errors":{},"meta":{}}'];
        yield 'object text inside a string value' => ['{"status":200,"data":{"x":"{\"a\":1,\"a\":2}","y":"[{\"a\":1},{\"a\":2}]"},"errors":{},"meta":{}}'];
        yield 'integer text inside a string value' => ['{"status":200,"data":{"x":"99999999999999999999","y":"-9223372036854775809 and 1e999"},"errors":{},"meta":{}}'];
        yield 'integer text as a key' => ['{"status":200,"data":{"99999999999999999999":1,"-9223372036854775809":2},"errors":{},"meta":{}}'];
        yield 'escaped backslashes before the closing quote' => ['{"status":200,"data":{"p":"\\\\","a":1,"q":"\\\\\\\\","b":2},"errors":{},"meta":{}}'];
    }

    #[DataProvider('keysThatAreNotTwice')]
    public function testKeysAndNumbersThatAreNotALossAreAnswers(string $body): void
    {
        $response = (new DecodeAnswer())($body, 200);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame($body, json_encode(EnvelopeJson::envelope($response), JSON_UNESCAPED_SLASHES));
    }

    public function testNestingThatTheDecoderRefusesIsNoJardisAnswer(): void
    {
        $this->expectException(NoJardisAnswerException::class);

        (new DecodeAnswer())(self::nested(520), 200);
    }

    /**
     * An envelope whose part is a list nested `$depth` deep (the envelope itself and the part count as two levels).
     */
    private static function nested(int $depth, string $part = 'data'): string
    {
        $value = str_repeat('[', $depth) . '1' . str_repeat(']', $depth);
        $parts = ['data' => '{}', 'errors' => '{}', 'meta' => '{}'];
        $parts[$part] = '{"a":' . $value . '}';

        return '{"status":' . ($part === 'errors' ? 400 : 200) . ',"data":' . $parts['data'] . ',"errors":' . $parts['errors']
            . ',"meta":' . $parts['meta'] . '}';
    }

    private static function nestedObjects(int $depth): string
    {
        $value = str_repeat('{"a":', $depth) . '1' . str_repeat('}', $depth);

        return '{"status":200,"data":{"a":' . $value . '},"errors":{},"meta":{}}';
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
