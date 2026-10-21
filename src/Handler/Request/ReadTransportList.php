<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request;

/**
 * Reads a REPEATED query parameter (`?keys[]=a&keys[]=b` or a single value). Absent or without any
 * element is `missing`; one element the accept closure refuses is a single `type` finding and the
 * list is empty.
 *
 * Atomic closure - one entry point.
 */
final class ReadTransportList
{
    /**
     * @template T
     * @param array<array-key, mixed> $source
     * @param callable(mixed): ?T $accept an `Accept\*` closure
     * @param list<array{field: string, reason: string}> $fields
     * @return list<T>
     */
    public function __invoke(array $source, string $name, callable $accept, array &$fields): array
    {
        if (!array_key_exists($name, $source)) {
            $fields[] = ['field' => $name, 'reason' => BuildRequestFindings::REASON_MISSING];

            return [];
        }
        $raw = $source[$name];
        $out = [];
        foreach (is_array($raw) ? array_values($raw) : [$raw] as $item) {
            $accepted = $accept($item);
            if ($accepted === null) {
                $fields[] = ['field' => $name, 'reason' => BuildRequestFindings::REASON_TYPE];

                return [];
            }
            $out[] = $accepted;
        }
        if ($out === []) {
            $fields[] = ['field' => $name, 'reason' => BuildRequestFindings::REASON_MISSING];
        }

        return $out;
    }
}
