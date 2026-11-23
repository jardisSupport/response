<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

use Closure;

/**
 * The count of a list that is not asked for yet: the closure runs when the count is first read, at
 * most once, and its number is kept. Held by `ReadResult` (the lazy list forms), which stays readonly
 * itself - this is the one mutable cell behind it. A closure that throws leaves nothing kept: the next
 * read asks again.
 */
final class LazyCount
{
    private ?int $counted = null;

    /**
     * @param Closure(): int $count
     */
    public function __construct(private readonly Closure $count)
    {
    }

    public function __invoke(): int
    {
        return $this->counted ??= ($this->count)();
    }
}
