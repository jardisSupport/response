<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request;

/**
 * Closes a request body: every key of the decoded JSON map that the contract does not name is a finding
 * of its own (`reason: unknown`), in body order - call it after the readers, so the unknown findings
 * follow the missing and type findings of the known fields.
 *
 * Atomic closure - one entry point.
 */
final class FindUnknownKeys
{
    /**
     * @param array<array-key, mixed> $source
     * @param list<string> $known
     * @param list<array{field: string, reason: string}> $fields
     */
    public function __invoke(array $source, array $known, array &$fields): void
    {
        foreach (array_keys($source) as $key) {
            if (!in_array($key, $known, true)) {
                $fields[] = ['field' => (string) $key, 'reason' => BuildRequestFindings::REASON_UNKNOWN];
            }
        }
    }
}
