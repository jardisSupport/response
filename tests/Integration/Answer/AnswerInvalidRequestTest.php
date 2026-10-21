<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Answer;

use DateTimeImmutable;
use JardisSupport\Factory\Factory;
use JardisSupport\Response\Handler\Answer\AnswerInvalidRequest;
use JardisSupport\Response\Handler\Request\Accept\BodyStr;
use JardisSupport\Response\Handler\Request\BuildRequestFindings;
use JardisSupport\Response\Handler\Request\FindUnknownKeys;
use JardisSupport\Response\Handler\Request\ReadRequired;
use JardisSupport\Response\Tests\Support\EnvelopeJson;
use JardisSupport\Scheduling\Clock\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * The 400 of a request that does not fit: the readers collect, `AnswerInvalidRequest` answers through the
 * answer closures of this package.
 *
 * Wire contract: `Fixture/envelope/route/s2-400.json` is the byte-fixed 400 of a generated route (T1 S2).
 * Origin: copy of `core/app/tests/Fixture/envelope/route/s2-400.json` (itself a copy of the Builder fixture
 * `tests/Fixture/envelope/route/s2-400.json`), pinned by SHA-256 below. Only `meta.duration` and
 * `meta.timestamp` are placeholders in the fixture; the test fixes the clock and substitutes both.
 */
final class AnswerInvalidRequestTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../Fixture/envelope/route/s2-400.json';
    private const string FIXTURE_SHA256 = 'b1b409692f174c01c07ed3ac6fc388d3e0ede35ee3be034310f1141dc2f35b08';

    public function testTheFixtureCopyIsPinned(): void
    {
        $this->assertSame(self::FIXTURE_SHA256, hash_file('sha256', self::FIXTURE));
    }

    /**
     * @return array<string, mixed> the envelope as the wire carries it (decoded)
     */
    private function wire(AnswerInvalidRequest $answer, string $field, string $version = ''): array
    {
        $fields = [];
        (new ReadRequired())(['other' => 'x'], $field, new BodyStr(), '', $fields);
        $response = $answer($fields, $version);

        $this->assertSame(400, $response->getStatus());
        $decoded = json_decode(EnvelopeJson::encode(EnvelopeJson::envelope($response)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function testTheBytesOfTheFixtureT1S2(): void
    {
        $now = new DateTimeImmutable('2026-10-20T10:00:00+00:00');
        $answer = new AnswerInvalidRequest(new FixedClock($now));

        $wire = $this->wire($answer, 'status');

        $expected = json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($expected);
        $this->assertSame('<elapsed>', $expected['meta']['duration']);
        $this->assertSame('<wall clock>', $expected['meta']['timestamp']);
        // a fixed clock stands still: the duration is zero (the wire writes it as the number 0)
        $this->assertEquals(0, $wire['meta']['duration']);
        $expected['meta']['duration'] = $wire['meta']['duration'];
        $expected['meta']['timestamp'] = $now->format('c');
        // assertSame on arrays compares keys, order and types: the bytes of the envelope.
        $this->assertSame($expected, $wire);
        $this->assertSame(json_encode($expected), json_encode($wire));
    }

    public function testTheVersionOfTheRequestIsInTheMeta(): void
    {
        $wire = $this->wire(new AnswerInvalidRequest(new FixedClock(new DateTimeImmutable())), 'status', 'v2');

        $this->assertSame('v2', $wire['meta']['version']);
    }

    public function testEveryReasonTheReadersCollectKeepsItsTextAndItsKey(): void
    {
        $findings = (new BuildRequestFindings())([
            ['field' => 'a', 'reason' => 'missing'],
            ['field' => 'items[1].b', 'reason' => 'type'],
            ['field' => 'c', 'reason' => 'unknown'],
            ['field' => 'd', 'reason' => 'anything-else'],
        ]);

        $this->assertSame([
            ['field' => 'a', 'reason' => 'missing', 'message' => 'Field is required.', 'messageKey' => 'validation.missing'],
            ['field' => 'items[1].b', 'reason' => 'type', 'message' => 'Field has an invalid type.', 'messageKey' => 'validation.type'],
            ['field' => 'c', 'reason' => 'unknown', 'message' => 'Unknown field', 'messageKey' => 'validation.unknown'],
            ['field' => 'd', 'reason' => 'anything-else', 'message' => 'Field is invalid.', 'messageKey' => 'validation.invalid'],
        ], $findings->fields);
        $this->assertSame([], $findings->errors);
    }

    public function testAllFaultsOfOneRequestAnswerAtOnce(): void
    {
        $fields = [];
        (new ReadRequired())([], 'a', new BodyStr(), '', $fields);
        (new ReadRequired())(['b' => 1], 'b', new BodyStr(), '', $fields);
        (new FindUnknownKeys())(['b' => 1, 'z' => 1], ['a', 'b'], $fields);

        $response = (new AnswerInvalidRequest(new FixedClock(new DateTimeImmutable())))($fields);

        $this->assertSame(400, $response->getStatus());
        $this->assertSame(['missing', 'type', 'unknown'], array_column($response->getData()['fields'], 'reason'));
    }

    public function testTheFindingsBuilderIsCreatableWithoutArgumentsAndTheAnswerTakesTheClock(): void
    {
        $factory = new Factory();

        $this->assertInstanceOf(BuildRequestFindings::class, $factory->get(BuildRequestFindings::class));
        $this->assertInstanceOf(
            AnswerInvalidRequest::class,
            $factory->create(AnswerInvalidRequest::class, new FixedClock(new DateTimeImmutable()))
        );
    }
}
