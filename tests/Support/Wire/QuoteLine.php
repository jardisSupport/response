<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support\Wire;

/**
 * One line of the quote input: the part of the generated input graph the 400 wire case needs.
 */
final readonly class QuoteLine
{
    /**
     * @param list<string>|null $serialNumbers
     */
    public function __construct(public string $unitPrice, public ?array $serialNumbers)
    {
    }
}
