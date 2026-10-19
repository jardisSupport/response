<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support\Response;

/**
 * Typed answer of a use case: two required fields and one optional.
 */
final readonly class ReserveStockResponse
{
    public function __construct(public string $reservationId, public int $quantity, public string $note = 'none')
    {
    }
}
