<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Response\Handler\Response\ProjectRows;
use PHPUnit\Framework\TestCase;

/**
 * The outer door cuts every row of a list down to the outer contract of the list.
 */
class ProjectRowsTest extends TestCase
{
    public function testCutsEveryRowToTheTwoNamedFields(): void
    {
        $rows = [
            ['id' => 1, 'orderNumber' => 'ORD-1', 'status' => 'open', 'total' => 10],
            ['id' => 2, 'orderNumber' => 'ORD-2', 'status' => 'paid', 'total' => 20],
        ];

        $cut = (new ProjectRows())($rows, 'orderNumber', 'status');

        $this->assertSame([
            ['orderNumber' => 'ORD-1', 'status' => 'open'],
            ['orderNumber' => 'ORD-2', 'status' => 'paid'],
        ], $cut);
    }

    public function testFieldOrderFollowsTheGivenNames(): void
    {
        $rows = [['id' => 1, 'orderNumber' => 'ORD-1', 'status' => 'open']];

        $cut = (new ProjectRows())($rows, 'status', 'id', 'orderNumber');

        $this->assertSame(['status', 'id', 'orderNumber'], array_keys($cut[0]));
    }

    public function testKeysThatAreNotNamedAreDropped(): void
    {
        $rows = [['orderNumber' => 'ORD-1', 'internalId' => 7, 'tenant' => 'x']];

        $cut = (new ProjectRows())($rows, 'orderNumber');

        $this->assertSame([['orderNumber' => 'ORD-1']], $cut);
    }

    public function testNullValueStaysNull(): void
    {
        $rows = [['orderNumber' => 'ORD-1', 'dueDate' => null]];

        $cut = (new ProjectRows())($rows, 'orderNumber', 'dueDate');

        $this->assertSame([['orderNumber' => 'ORD-1', 'dueDate' => null]], $cut);
        $this->assertArrayHasKey('dueDate', $cut[0]);
    }

    public function testNoRowsGiveNoRows(): void
    {
        $this->assertSame([], (new ProjectRows())([], 'orderNumber'));
    }

    public function testRowsThatAreNoListAreRefused(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new ProjectRows())(['a' => ['orderNumber' => 'ORD-1']], 'orderNumber');
    }

    public function testRowThatIsNoArrayIsRefusedWithItsIndex(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Row 1 is not an array');

        (new ProjectRows())([['orderNumber' => 'ORD-1'], 'ORD-2'], 'orderNumber');
    }

    public function testMissingKeyIsRefusedWithIndexAndField(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Row 2 lacks the field "status"');

        (new ProjectRows())([
            ['orderNumber' => 'ORD-1', 'status' => 'open'],
            ['orderNumber' => 'ORD-2', 'status' => 'paid'],
            ['orderNumber' => 'ORD-3'],
        ], 'orderNumber', 'status');
    }

    public function testCutWithoutAnyFieldIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ProjectRows())([['orderNumber' => 'ORD-1']]);
    }
}
