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
use JardisSupport\Validation\Validator\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The 15 accept closures, one table: a value the type takes (and what it becomes) and values it
 * refuses. The rows are the type rules of the request helpers every generated `routes.php` carried:
 * a transport value (path/query) is a string end to end and is validated first, coerced second; a
 * body value keeps its JSON type and is strict. The calendar forms are judged by the one parser of
 * `jardissupport/validation` (real, no mock), injected through the constructor (`ValueValidatorInterface`).
 */
final class AcceptTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(mixed): mixed, mixed, list<mixed>}>
     */
    public static function types(): iterable
    {
        yield 'TransportStr' => [new TransportStr(), 'abc', ['', 1, null, ['a']]];
        yield 'TransportInt' => [new TransportInt(), '-42', ['', '1.5', '1e3', ' 1', 7, null, '12a']];
        yield 'TransportFloat' => [new TransportFloat(), '1.5', ['abc', '', 1.5, null, ['1']]];
        yield 'TransportDecimal' => [new TransportDecimal(), '19.99', ['19,99', 'x', '', 19.99, null]];
        yield 'TransportDecimal limited' => [new TransportDecimal('^\d{1,3}(\.\d{1,2})?$'), '123.45', ['1234.5', '1.234']];
        yield 'TransportBool true' => [new TransportBool(), 'true', ['yes', 'TRUE', '', true, 1, null]];
        yield 'TransportBool 0' => [new TransportBool(), '0', ['2']];
        yield 'TransportDate' => [new TransportDate(new DateTime()), '2026-02-28', ['2026-02-30', '28.02.2026', '', 20260228, null]];
        yield 'TransportDateTime' => [
            new TransportDateTime(new DateTime()),
            '2026-02-28T10:00:00+01:00',
            ['2026-02-28T10:00:00', '2026-02-28', '2026-02-30T10:00:00Z', '', null],
        ];
        yield 'TransportTime' => [new TransportTime(new DateTime()), '23:59:59', ['24:00:00', '10:00', '', null]];
        yield 'BodyStr' => [new BodyStr(), '', [1, 1.5, true, null, ['a']]];
        yield 'BodyInt' => [new BodyInt(), 5, ['5', 1.5, true, null]];
        yield 'BodyFloat int' => [new BodyFloat(), 5, ['5', true, null]];
        yield 'BodyFloat fraction' => [new BodyFloat(), 1.5, ['1.5']];
        yield 'BodyBool' => [new BodyBool(), false, ['false', 0, 1, null]];
        yield 'BodyDate' => [new BodyDate(new DateTime()), '2026-02-28', ['2026-02-30', 20260228, '', null]];
        yield 'BodyDateTime' => [
            new BodyDateTime(new DateTime()),
            '2026-02-28T10:00:00+01:00',
            ['2026-02-28T10:00:00', '2026-02-30T10:00:00Z', 1, null],
        ];
        yield 'BodyTime' => [new BodyTime(new DateTime()), '10:11:12', ['10:11', 1, null]];
    }

    /**
     * @param list<mixed> $refused
     */
    #[DataProvider('types')]
    public function testTakesTheValidFormAndRefusesTheRest(callable $accept, mixed $valid, array $refused): void
    {
        $accepted = $accept($valid);

        $this->assertNotNull($accepted);
        foreach ($refused as $value) {
            $this->assertNull($accept($value), get_debug_type($accept) . ' must refuse ' . var_export($value, true));
        }
    }

    public function testTransportCoercesAfterValidating(): void
    {
        $this->assertSame(-42, (new TransportInt())('-42'));
        $this->assertSame(1.5, (new TransportFloat())('1.5'));
        $this->assertTrue((new TransportBool())('true'));
        $this->assertTrue((new TransportBool())('1'));
        $this->assertFalse((new TransportBool())('false'));
        $this->assertFalse((new TransportBool())('0'));
        $this->assertSame('19.99', (new TransportDecimal())('19.99'));
        $this->assertSame('2026-02-28', (new TransportDate(new DateTime()))('2026-02-28'));
    }

    public function testBodyKeepsTheJsonTypeAndTheInstantIsBuilt(): void
    {
        $this->assertSame('', (new BodyStr())(''));
        $this->assertSame(5, (new BodyInt())(5));
        $this->assertSame(5.0, (new BodyFloat())(5));
        $this->assertFalse((new BodyBool())(false));
        $this->assertSame('2026-02-28', (new BodyDate(new DateTime()))('2026-02-28')?->format('Y-m-d'));
        $this->assertSame('10:11:12', (new BodyTime(new DateTime()))('10:11:12')?->format('H:i:s'));
    }

    public function testADateTimeBodyValueIsNormalisedToUtc(): void
    {
        $instant = (new BodyDateTime(new DateTime()))('2026-02-28T10:00:00+01:00');

        $this->assertNotNull($instant);
        $this->assertSame('UTC', $instant->getTimezone()->getName());
        $this->assertSame('2026-02-28 09:00:00', $instant->format('Y-m-d H:i:s'));
    }
}
