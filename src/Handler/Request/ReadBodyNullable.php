<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request;

/**
 * Reads a NULLABLE value of the decoded JSON body. Absent or an explicit `null` is "not given" (null);
 * a value the accept closure refuses is a `type` finding (collected) and also null.
 *
 * Atomic closure - one entry point.
 */
final class ReadBodyNullable
{
    /**
     * @template T
     * @param array<array-key, mixed> $body
     * @param callable(mixed): ?T $accept an `Accept\*` closure
     * @param list<array{field: string, reason: string}> $fields
     * @return T|null
     */
    public function __invoke(array $body, string $name, callable $accept, array &$fields): mixed
    {
        $value = $body[$name] ?? null;
        if ($value === null) {
            return null;
        }
        $accepted = $accept($value);
        if ($accepted === null) {
            $fields[] = ['field' => $name, 'reason' => BuildRequestFindings::REASON_TYPE];
        }

        return $accepted;
    }
}
