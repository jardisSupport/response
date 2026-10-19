<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support\Wire;

/**
 * Input of the quote use case: delivery channels from a value list and a list of lines.
 */
final readonly class QuoteInput
{
    /**
     * @param list<string>|null $deliveryChannels
     * @param list<QuoteLine> $lines
     */
    public function __construct(public ?array $deliveryChannels, public array $lines)
    {
    }
}
