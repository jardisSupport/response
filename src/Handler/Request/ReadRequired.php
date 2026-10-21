<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request;

/**
 * Reads a REQUIRED value of a source - the path/query values or the decoded JSON body; the accept
 * closure decides what a value of that source is. Absent is a `missing` finding (an explicit `null`
 * is present and goes to the accept closure), a value the accept closure refuses is a `type` finding;
 * either way the finding is collected (not returned early, so one request answers with all its faults
 * at once) and the fallback stands in for the value.
 *
 * Atomic closure - one entry point.
 */
final class ReadRequired
{
    /**
     * @template T
     * @param array<array-key, mixed> $source the path/query values or the decoded body
     * @param callable(mixed): ?T $accept an `Accept\*` closure
     * @param T $fallback
     * @param list<array{field: string, reason: string}> $fields
     * @return T
     */
    public function __invoke(array $source, string $name, callable $accept, mixed $fallback, array &$fields): mixed
    {
        if (!array_key_exists($name, $source)) {
            $fields[] = ['field' => $name, 'reason' => BuildRequestFindings::REASON_MISSING];

            return $fallback;
        }
        $accepted = $accept($source[$name]);
        if ($accepted === null) {
            $fields[] = ['field' => $name, 'reason' => BuildRequestFindings::REASON_TYPE];

            return $fallback;
        }

        return $accepted;
    }
}
