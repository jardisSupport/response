<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Data;

use JardisSupport\Response\Data\ReadResult;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * ReadResult<T>: list form with total, single form found/empty.
 */
class ReadResultTest extends TestCase
{
    public function testClassIsFinalAndReadonly(): void
    {
        $reflection = new ReflectionClass(ReadResult::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testOfListKeepsItemsAndTotalIndependently(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();

        $result = ReadResult::ofList([$a, $b], 42);

        $this->assertSame([$a, $b], $result->items());
        $this->assertSame(42, $result->total());
        $this->assertSame($a, $result->first());
        $this->assertFalse($result->isEmpty());
    }

    public function testOfListEmptyIsEmptyWithItsTotal(): void
    {
        $result = ReadResult::ofList([], 0);

        $this->assertSame([], $result->items());
        $this->assertSame(0, $result->total());
        $this->assertNull($result->first());
        $this->assertTrue($result->isEmpty());
    }

    public function testOfListRejectsTotalSmallerThanItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReadResult::ofList(['a', 'b'], 1);
    }

    public function testOfOneFoundIsAListOfOne(): void
    {
        $item = new \stdClass();

        $result = ReadResult::ofOne($item);

        $this->assertSame([$item], $result->items());
        $this->assertSame(1, $result->total());
        $this->assertSame($item, $result->first());
        $this->assertFalse($result->isEmpty());
    }

    public function testOfOneNullIsEmpty(): void
    {
        $result = ReadResult::ofOne(null);

        $this->assertSame([], $result->items());
        $this->assertSame(0, $result->total());
        $this->assertNull($result->first());
        $this->assertTrue($result->isEmpty());
    }

    public function testOfCappedListUnderTheCapKeepsTheExactCount(): void
    {
        $result = ReadResult::ofCappedList(['a', 'b'], 7, 10);

        $this->assertSame(['a', 'b'], $result->items());
        $this->assertSame(7, $result->total());
        $this->assertFalse($result->isCapped());
    }

    public function testOfCappedListExactlyAtTheCapIsNotCapped(): void
    {
        $result = ReadResult::ofCappedList(['a'], 10, 10);

        $this->assertSame(10, $result->total());
        $this->assertFalse($result->isCapped());
    }

    public function testOfCappedListBeyondTheCapReportsTheCapAndIsCapped(): void
    {
        $result = ReadResult::ofCappedList(['a', 'b'], 11, 10);

        $this->assertSame(['a', 'b'], $result->items());
        $this->assertSame(10, $result->total());
        $this->assertTrue($result->isCapped());
    }

    public function testOfCappedListRejectsCapSmallerThanItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReadResult::ofCappedList(['a', 'b', 'c'], 5, 2);
    }

    public function testOfCappedListAcceptsAnEmptyListWithCapZero(): void
    {
        $result = ReadResult::ofCappedList([], 0, 0);

        $this->assertSame(0, $result->total());
        $this->assertFalse($result->isCapped());
        $this->assertTrue($result->isEmpty());
    }

    public function testOfListAndOfOneAreNeverCapped(): void
    {
        $this->assertFalse(ReadResult::ofList(['a'], 99)->isCapped());
        $this->assertFalse(ReadResult::ofOne('a')->isCapped());
        $this->assertFalse(ReadResult::ofOne(null)->isCapped());
    }
}
