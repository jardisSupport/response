<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request;

/**
 * Re-labels the findings collected while reading a NESTED payload onto the path that carries it -
 * `<list>[1].<field>` instead of a bare `<field>` - and appends them to the findings of the request.
 * The nested read collects into its own list, so the prefix is applied once, at the carrying site.
 *
 * Atomic closure - one entry point.
 */
final class RebaseFindings
{
    /**
     * @param list<array{field: string, reason: string}> $found
     * @param list<array{field: string, reason: string}> $fields
     */
    public function __invoke(string $path, array $found, array &$fields): void
    {
        foreach ($found as $finding) {
            $fields[] = ['field' => $path . '.' . $finding['field'], 'reason' => $finding['reason']];
        }
    }
}
