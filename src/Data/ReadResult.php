<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

/**
 * Typed read answer: a list of items with the total count, or a single optional item.
 *
 * @template T
 */
final readonly class ReadResult
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        private array $items,
        private int $total,
        private bool $capped = false,
    ) {
    }

    /**
     * @template U
     * @param list<U> $items
     * @return self<U>
     * @throws \InvalidArgumentException when $total is smaller than the number of items
     */
    public static function ofList(array $items, int $total): self
    {
        if ($total < count($items)) {
            throw new \InvalidArgumentException(sprintf(
                'Total %d is smaller than the %d given items',
                $total,
                count($items)
            ));
        }

        return new self($items, $total);
    }

    /**
     * A list whose count stops at a cap: `$counted` is how many rows the count reached (it may stop at
     * `cap + 1`, the exact number is not needed). At most `$cap` is reported: `total()` is `$counted`
     * while it does not exceed the cap, else the cap, and `isCapped()` says the cap was hit.
     *
     * @template U
     * @param list<U> $items
     * @return self<U>
     * @throws \InvalidArgumentException when $cap is smaller than the number of items
     */
    public static function ofCappedList(array $items, int $counted, int $cap): self
    {
        if ($cap < count($items)) {
            throw new \InvalidArgumentException(sprintf(
                'Cap %d is smaller than the %d given items',
                $cap,
                count($items)
            ));
        }

        $capped = $counted > $cap;

        return new self($items, $capped ? $cap : $counted, $capped);
    }

    /**
     * @template U
     * @param U|null $item
     * @return self<U>
     */
    public static function ofOne(mixed $item): self
    {
        /** @var list<U> $items */
        $items = $item === null ? [] : [$item];

        return new self($items, count($items));
    }

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * True when the count was cut at a cap: `total()` is then the cap, the real number is higher.
     */
    public function isCapped(): bool
    {
        return $this->capped;
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
