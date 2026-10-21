<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Request;

use JardisSupport\Response\Handler\Request\Accept\BodyBool;
use JardisSupport\Response\Handler\Request\Accept\BodyDate;
use JardisSupport\Response\Handler\Request\Accept\BodyDateTime;
use JardisSupport\Response\Handler\Request\Accept\BodyFloat;
use JardisSupport\Response\Handler\Request\Accept\BodyInt;
use JardisSupport\Response\Handler\Request\Accept\BodyStr;
use JardisSupport\Response\Handler\Request\Accept\BodyTime;
use JardisSupport\Response\Handler\Request\Accept\TransportBool;
use JardisSupport\Response\Handler\Request\Accept\TransportDate;
use JardisSupport\Response\Handler\Request\Accept\TransportDateTime;
use JardisSupport\Response\Handler\Request\Accept\TransportDecimal;
use JardisSupport\Response\Handler\Request\Accept\TransportFloat;
use JardisSupport\Response\Handler\Request\Accept\TransportInt;
use JardisSupport\Response\Handler\Request\Accept\TransportStr;
use JardisSupport\Response\Handler\Request\Accept\TransportTime;
use JardisSupport\Response\Handler\Request\FindUnknownKeys;
use JardisSupport\Response\Handler\Request\ReadBodyNullable;
use JardisSupport\Response\Handler\Request\ReadTransportList;
use JardisSupport\Response\Handler\Request\ReadTransportOptional;
use JardisSupport\Response\Handler\Request\ReadRequired;
use JardisSupport\Response\Handler\Request\RebaseFindings;
use JardisSupport\Factory\Factory;
use JardisSupport\Validation\Validator\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The readers around the accept closures, per type: a valid value, an invalid one, a missing one.
 * A finding is COLLECTED (never returned early), the fallback or null stands in for the value, and
 * every reader is creatable without arguments (G12) - the way a generated door gets them; the calendar
 * accept closures take the value validator in the constructor.
 */
final class ReadRequestTest extends TestCase
{
    private function make(string $class): object
    {
        $made = (new Factory())->get($class);
        $this->assertIsObject($made);

        return $made;
    }

    /**
     * The calendar accept closures judge the form with an injected value validator (the real `DateTime`
     * validator of `jardissupport/validation`); every other closure is creatable without arguments.
     */
    private function build(string $class): object
    {
        return in_array($class, [
            TransportDate::class, TransportDateTime::class, TransportTime::class,
            BodyDate::class, BodyDateTime::class, BodyTime::class,
        ], true) ? new $class(new DateTime()) : $this->make($class);
    }

    /**
     * @param class-string $class
     * @return callable(mixed): mixed
     */
    private function accept(string $class): callable
    {
        $made = $this->build($class);
        $this->assertIsCallable($made);

        return $made;
    }

    /**
     * @return iterable<string, array{class-string, mixed, mixed, mixed}>
     *   accept, valid raw value, invalid raw value, fallback
     */
    public static function requiredTransport(): iterable
    {
        yield 'reqStr' => [TransportStr::class, 'abc', '', ''];
        yield 'reqInt' => [TransportInt::class, '7', 'x', 0];
        yield 'reqFloat' => [TransportFloat::class, '1.5', 'x', 0.0];
        yield 'reqDecimal' => [TransportDecimal::class, '19.99', '19,99', '0'];
        yield 'reqBool' => [TransportBool::class, 'true', 'maybe', false];
        yield 'reqDate' => [TransportDate::class, '2026-02-28', '2026-02-30', ''];
        yield 'reqDateTime' => [TransportDateTime::class, '2026-02-28T10:00:00Z', '2026-02-28', ''];
        yield 'reqTime' => [TransportTime::class, '10:00:00', '25:00:00', ''];
    }

    /**
     * @param class-string $accept
     */
    #[DataProvider('requiredTransport')]
    public function testRequiredTransportValidMissingInvalid(
        string $accept,
        string $valid,
        string $invalid,
        mixed $fallback
    ): void
    {
        $read = $this->make(ReadRequired::class);
        $accepter = $this->accept($accept);
        $this->assertInstanceOf(ReadRequired::class, $read);

        $fields = [];
        $value = $read(['p' => $valid], 'p', $accepter, $fallback, $fields);
        $this->assertSame([], $fields);
        $this->assertNotSame($fallback, $value);

        $fields = [];
        $this->assertSame($fallback, $read([], 'p', $accepter, $fallback, $fields));
        $this->assertSame([['field' => 'p', 'reason' => 'missing']], $fields);

        $fields = [];
        $this->assertSame($fallback, $read(['p' => $invalid], 'p', $accepter, $fallback, $fields));
        $this->assertSame([['field' => 'p', 'reason' => 'type']], $fields);
    }

    /**
     * @return iterable<string, array{class-string, string, string|list<string>}>
     */
    public static function optionalTransport(): iterable
    {
        yield 'optStr' => [TransportStr::class, 'abc', ['a']]; // a non-string can only come from a query array
        yield 'optInt' => [TransportInt::class, '7', 'x'];
        yield 'optFloat' => [TransportFloat::class, '1.5', 'x'];
        yield 'optDecimal' => [TransportDecimal::class, '19.99', '19,99'];
        yield 'optBool' => [TransportBool::class, 'false', 'maybe'];
        yield 'optDate' => [TransportDate::class, '2026-02-28', '2026-02-30'];
        yield 'optDateTime' => [TransportDateTime::class, '2026-02-28T10:00:00Z', '2026-02-28'];
        yield 'optTime' => [TransportTime::class, '10:00:00', '25:00:00'];
    }

    /**
     * @param class-string $accept
     * @param string|list<string> $invalid
     */
    #[DataProvider('optionalTransport')]
    public function testOptionalTransportValidAbsentInvalid(string $accept, string $valid, string|array $invalid): void
    {
        $read = $this->make(ReadTransportOptional::class);
        $accepter = $this->accept($accept);
        $this->assertInstanceOf(ReadTransportOptional::class, $read);

        $fields = [];
        $this->assertNotNull($read(['p' => $valid], 'p', $accepter, $fields));
        $this->assertSame([], $fields);

        foreach ([[], ['p' => ''], ['p' => null]] as $absent) {
            $fields = [];
            $this->assertNull($read($absent, 'p', $accepter, $fields));
            $this->assertSame([], $fields);
        }

        $fields = [];
        $this->assertNull($read(['p' => $invalid], 'p', $accepter, $fields));
        $this->assertSame([['field' => 'p', 'reason' => 'type']], $fields);
    }

    /**
     * @return iterable<string, array{class-string, mixed, mixed, mixed}>
     */
    public static function requiredBody(): iterable
    {
        yield 'bodyStr' => [BodyStr::class, 'abc', 5, ''];
        yield 'bodyInt' => [BodyInt::class, 5, '5', 0];
        yield 'bodyFloat' => [BodyFloat::class, 1.5, '1.5', 0.0];
        yield 'bodyBool' => [BodyBool::class, true, 'true', false];
        yield 'bodyDate' => [BodyDate::class, '2026-02-28', '2026-02-30', null];
        yield 'bodyDateTime' => [BodyDateTime::class, '2026-02-28T10:00:00Z', '2026-02-28', null];
        yield 'bodyTime' => [BodyTime::class, '10:00:00', '25:00:00', null];
    }

    /**
     * @param class-string $accept
     */
    #[DataProvider('requiredBody')]
    public function testRequiredBodyValidMissingInvalid(
        string $accept,
        mixed $valid,
        mixed $invalid,
        mixed $fallback
    ): void
    {
        $read = $this->make(ReadRequired::class);
        $accepter = $this->accept($accept);
        $this->assertInstanceOf(ReadRequired::class, $read);
        $fallback ??= new \DateTimeImmutable('@0');

        $fields = [];
        $value = $read(['f' => $valid], 'f', $accepter, $fallback, $fields);
        $this->assertSame([], $fields);
        $this->assertNotEquals($fallback, $value);

        $fields = [];
        $this->assertSame($fallback, $read([], 'f', $accepter, $fallback, $fields));
        $this->assertSame([['field' => 'f', 'reason' => 'missing']], $fields);

        $fields = [];
        $this->assertSame($fallback, $read(['f' => $invalid], 'f', $accepter, $fallback, $fields));
        $this->assertSame([['field' => 'f', 'reason' => 'type']], $fields);
    }

    /**
     * @param class-string $accept
     */
    #[DataProvider('requiredBody')]
    public function testNullableBodyValidNullAbsentInvalid(string $accept, mixed $valid, mixed $invalid): void
    {
        $read = $this->make(ReadBodyNullable::class);
        $accepter = $this->accept($accept);
        $this->assertInstanceOf(ReadBodyNullable::class, $read);

        $fields = [];
        $this->assertNotNull($read(['f' => $valid], 'f', $accepter, $fields));
        $this->assertSame([], $fields);

        foreach ([[], ['f' => null]] as $absent) {
            $fields = [];
            $this->assertNull($read($absent, 'f', $accepter, $fields));
            $this->assertSame([], $fields);
        }

        $fields = [];
        $this->assertNull($read(['f' => $invalid], 'f', $accepter, $fields));
        $this->assertSame([['field' => 'f', 'reason' => 'type']], $fields);
    }

    public function testABodyFalseIsAValueNotAnAbsence(): void
    {
        $fields = [];
        $required = (new ReadRequired())(['f' => false], 'f', new BodyBool(), true, $fields);
        $nullable = (new ReadBodyNullable())(['f' => false], 'f', new BodyBool(), $fields);

        $this->assertFalse($required);
        $this->assertFalse($nullable);
        $this->assertSame([], $fields);
    }

    public function testStrListAndIntListCollectOneFindingForARefusedElement(): void
    {
        $read = $this->make(ReadTransportList::class);
        $this->assertInstanceOf(ReadTransportList::class, $read);

        $fields = [];
        $this->assertSame(['a', 'b'], $read(['keys' => ['a', 'b']], 'keys', new TransportStr(), $fields));
        $this->assertSame(['a'], $read(['keys' => 'a'], 'keys', new TransportStr(), $fields));
        $this->assertSame([1, -2], $read(['ids' => ['1', '-2']], 'ids', new TransportInt(), $fields));
        $this->assertSame([], $fields);

        $this->assertSame([], $read([], 'keys', new TransportStr(), $fields));
        $this->assertSame([], $read(['keys' => []], 'keys', new TransportStr(), $fields));
        $this->assertSame([], $read(['ids' => ['1', 'x', 'y']], 'ids', new TransportInt(), $fields));
        $this->assertSame([], $read(['keys' => ['a', '']], 'keys', new TransportStr(), $fields));
        $this->assertSame([
            ['field' => 'keys', 'reason' => 'missing'],
            ['field' => 'keys', 'reason' => 'missing'],
            ['field' => 'ids', 'reason' => 'type'],
            ['field' => 'keys', 'reason' => 'type'],
        ], $fields);
    }

    public function testUnknownKeysFollowTheKnownFindingsInBodyOrder(): void
    {
        $body = ['b' => 1, 'name' => 'x', 'a' => 2];
        $fields = [['field' => 'name', 'reason' => 'type']];

        (new FindUnknownKeys())($body, ['name'], $fields);

        $this->assertSame([
            ['field' => 'name', 'reason' => 'type'],
            ['field' => 'b', 'reason' => 'unknown'],
            ['field' => 'a', 'reason' => 'unknown'],
        ], $fields);
    }

    public function testRebaseLabelsNestedFindingsWithTheCarryingPath(): void
    {
        $fields = [['field' => 'x', 'reason' => 'missing']];

        (new RebaseFindings())('items[1]', [['field' => 'qty', 'reason' => 'type']], $fields);

        $this->assertSame([
            ['field' => 'x', 'reason' => 'missing'],
            ['field' => 'items[1].qty', 'reason' => 'type'],
        ], $fields);
    }
}
