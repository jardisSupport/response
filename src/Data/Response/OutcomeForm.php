<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data\Response;

/**
 * The outer contract of one outcome of a process answer: which fields the outcome's answer keeps,
 * in which order, and how the rows of its lists are cut.
 *
 * A pure value - it carries names only. The wire projection of the values is not its business
 * (that is `ProjectWire` of the data package, wired by the caller).
 */
final readonly class OutcomeForm
{
    /**
     * @param list<string> $fields field names of the outcome, in the order they appear on the wire
     * @param array<string, list<string>> $rowCuts field => row keys the rows of that list keep
     */
    public function __construct(
        public array $fields,
        public array $rowCuts = [],
    ) {
    }
}
