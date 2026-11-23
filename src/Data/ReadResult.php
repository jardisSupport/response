<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

use Closure;

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
        private ?LazyCount $count = null,
        private ?int $cap = null,
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
        self::requireCapFits($items, $cap);

        $capped = $counted > $cap;

        return new self($items, $capped ? $cap : $counted, $capped);
    }

    /**
     * A list whose total is counted only when someone reads it: `$count` runs at the first `total()`, at
     * most once, and its number is kept; without a read it never runs. Same check as `ofList`, made at the
     * first read of the total.
     *
     * @template U
     * @param list<U> $items
     * @param Closure(): int $count
     * @return self<U>
     */
    public static function ofCountedList(array $items, Closure $count): self
    {
        return new self($items, 0, false, new LazyCount($count));
    }

    /**
     * The lazy form of `ofCappedList`: `$count` returns how many rows the count reached (it may stop at
     * `cap + 1`); it runs at the first `total()` or `isCapped()`, at most once, both answers come from that
     * one run. Without a read it never runs.
     *
     * @template U
     * @param list<U> $items
     * @param Closure(): int $count
     * @return self<U>
     * @throws \InvalidArgumentException when $cap is smaller than the number of items
     */
    public static function ofCappedCountedList(array $items, Closure $count, int $cap): self
    {
        self::requireCapFits($items, $cap);

        return new self($items, 0, false, new LazyCount($count), $cap);
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
        if ($this->count === null) {
            return $this->total;
        }

        $counted = ($this->count)();
        if ($this->cap !== null) {
            return min($counted, $this->cap);
        }
        if ($counted < count($this->items)) {
            throw new \InvalidArgumentException(sprintf(
                'Total %d is smaller than the %d given items',
                $counted,
                count($this->items)
            ));
        }

        return $counted;
    }

    /**
     * True when the count was cut at a cap: `total()` is then the cap, the real number is higher.
     */
    public function isCapped(): bool
    {
        if ($this->count === null || $this->cap === null) {
            return $this->capped;
        }

        return ($this->count)() > $this->cap;
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

    /**
     * @param list<mixed> $items
     * @throws \InvalidArgumentException when $cap is smaller than the number of items
     */
    private static function requireCapFits(array $items, int $cap): void
    {
        if ($cap < count($items)) {
            throw new \InvalidArgumentException(sprintf(
                'Cap %d is smaller than the %d given items',
                $cap,
                count($items)
            ));
        }
    }
}
