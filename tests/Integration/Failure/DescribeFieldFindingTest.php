<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Failure;

use JardisSupport\Response\Handler\Failure\DescribeFieldFinding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * DescribeFieldFinding is the one place of the vocabulary of a field finding in the 400 answer.
 */
class DescribeFieldFindingTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function reasons(): array
    {
        return [
            'missing' => ['missing', 'Field is required.', 'validation.missing'],
            'invalid' => ['invalid', 'Field is invalid.', 'validation.invalid'],
            'type' => ['type', 'Field has an invalid type.', 'validation.type'],
            'unknown' => ['unknown', 'Unknown field', 'validation.unknown'],
            'any other reason is answered as invalid' => ['anything-else', 'Field is invalid.', 'validation.invalid'],
        ];
    }

    #[DataProvider('reasons')]
    public function testEveryReasonGetsItsStandardTextAndItsKey(string $reason, string $text, string $key): void
    {
        $described = (new DescribeFieldFinding())(['field' => 'items[1].qty', 'reason' => $reason, 'message' => '']);

        $this->assertSame(
            ['field' => 'items[1].qty', 'reason' => $reason, 'message' => $text, 'messageKey' => $key],
            $described
        );
    }

    #[DataProvider('reasons')]
    public function testTheTextOfTheCallerStaysUntouched(string $reason, string $text, string $key): void
    {
        $described = (new DescribeFieldFinding())(['field' => 'a', 'reason' => $reason, 'message' => 'Too small']);

        $this->assertSame('Too small', $described['message']);
        $this->assertSame($key, $described['messageKey']);
        $this->assertSame($reason, $described['reason']);
    }
}
