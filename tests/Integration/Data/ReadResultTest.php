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

    /**
     * @return array{0: \Closure(): int, 1: \stdClass} a count closure and its call counter in `->calls`
     */
    private function counter(int $number): array
    {
        $probe = new \stdClass();
        $probe->calls = 0;

        return [
            static function () use ($probe, $number): int {
                $probe->calls++;

                return $number;
            },
            $probe,
        ];
    }

    public function testCountedListNeverCountsWithoutAReadOfTheTotal(): void
    {
        [$count, $probe] = $this->counter(7);

        $result = ReadResult::ofCountedList(['a', 'b'], $count);
        $result->items();
        $result->first();
        $result->isEmpty();

        $this->assertSame(0, $probe->calls);
    }

    public function testCountedListCountsExactlyOnceHoweverOftenTheTotalIsRead(): void
    {
        [$count, $probe] = $this->counter(7);

        $result = ReadResult::ofCountedList(['a', 'b'], $count);

        $this->assertSame(7, $result->total());
        $this->assertSame(7, $result->total());
        $this->assertSame(7, $result->total());
        $this->assertFalse($result->isCapped());
        $this->assertSame(1, $probe->calls);
    }

    public function testCountedListKeepsItemsAndReadsLikeOfList(): void
    {
        [$count] = $this->counter(2);

        $result = ReadResult::ofCountedList(['a', 'b'], $count);

        $this->assertSame(['a', 'b'], $result->items());
        $this->assertSame('a', $result->first());
        $this->assertFalse($result->isEmpty());
        $this->assertSame(2, $result->total());
    }

    public function testCountedListRejectsATotalSmallerThanItemsAtTheFirstReadOfTheTotal(): void
    {
        [$count, $probe] = $this->counter(1);

        $result = ReadResult::ofCountedList(['a', 'b'], $count);
        $this->assertSame(['a', 'b'], $result->items());
        $this->assertSame(0, $probe->calls);

        try {
            $result->total();
            $this->fail('the check of the total must throw');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Total 1 is smaller than the 2 given items', $e->getMessage());
        }
        $this->assertSame(1, $probe->calls);
    }

    public function testCountedListAsksAgainAfterAClosureThatThrew(): void
    {
        $calls = 0;
        $result = ReadResult::ofCountedList(['a'], static function () use (&$calls): int {
            if (++$calls === 1) {
                throw new \RuntimeException('database down');
            }

            return 3;
        });

        try {
            $result->total();
            $this->fail('the failure of the count must reach the reader');
        } catch (\RuntimeException) {
        }

        $this->assertSame(3, $result->total());
        $this->assertSame(3, $result->total());
        $this->assertSame(2, $calls);
    }

    public function testCountedListIsAnEmptyListWithItsTotal(): void
    {
        [$count] = $this->counter(0);

        $result = ReadResult::ofCountedList([], $count);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->total());
    }

    public function testCappedCountedListNeverCountsWithoutARead(): void
    {
        [$count, $probe] = $this->counter(11);

        $result = ReadResult::ofCappedCountedList(['a'], $count, 10);
        $result->items();
        $result->first();
        $result->isEmpty();

        $this->assertSame(0, $probe->calls);
    }

    public function testCappedCountedListAboveTheCapReportsTheCapFromOneCount(): void
    {
        [$count, $probe] = $this->counter(11);

        $result = ReadResult::ofCappedCountedList(['a', 'b'], $count, 10);

        $this->assertTrue($result->isCapped());
        $this->assertSame(10, $result->total());
        $this->assertTrue($result->isCapped());
        $this->assertSame(10, $result->total());
        $this->assertSame(1, $probe->calls);
    }

    public function testCappedCountedListAtOrBelowTheCapReportsTheExactTotal(): void
    {
        [$count, $probe] = $this->counter(10);

        $result = ReadResult::ofCappedCountedList(['a'], $count, 10);

        $this->assertSame(10, $result->total());
        $this->assertFalse($result->isCapped());
        $this->assertSame(1, $probe->calls);

        [$below] = $this->counter(4);
        $under = ReadResult::ofCappedCountedList(['a'], $below, 10);
        $this->assertFalse($under->isCapped());
        $this->assertSame(4, $under->total());
    }

    public function testCappedCountedListRejectsCapSmallerThanItemsAtOnceWithoutCounting(): void
    {
        [$count, $probe] = $this->counter(5);

        try {
            ReadResult::ofCappedCountedList(['a', 'b', 'c'], $count, 2);
            $this->fail('a cap below the items must throw');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, $probe->calls);
    }

    public function testEagerFormsAreUnchangedAndKeepNoCell(): void
    {
        $this->assertSame(5, ReadResult::ofList(['a'], 5)->total());
        $this->assertSame(5, ReadResult::ofCappedList(['a'], 5, 9)->total());
        $this->assertSame(1, ReadResult::ofOne('a')->total());
    }
}
