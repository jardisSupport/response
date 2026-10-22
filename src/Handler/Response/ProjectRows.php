<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

/**
 * Cuts every row of a list down to the outer contract of the list.
 *
 * Inside, a row may carry more than the outside promises (e.g. the internal
 * identifier next to the public key). Each row of the result holds exactly the
 * named keys, in the order the names are given; nothing is added, a `null`
 * value stays `null`.
 *
 * Deliberate difference to {@see ProjectAnswer}: there an unknown name is no
 * error. Here a named key that a row does not carry is drift between the
 * declared contract and the data, and it fails loudly with an
 * `\UnexpectedValueException` naming the row index and the field - the door
 * answers it through `MapThrowable` as 500. The same holds for rows that are
 * not a list and for a row that is not an array. A cut without any field is a
 * wiring error of the caller (`\InvalidArgumentException`, fail closed).
 *
 * The closure knows no field name of its own - only the list it is given.
 *
 * Atomic closure - one entry point.
 */
final class ProjectRows
{
    /**
     * @param array<array-key, mixed> $rows the rows to cut, a list of arrays
     * @return list<array<string, mixed>> every row reduced to the given fields, in the order of `$fields`
     *
     * @throws \InvalidArgumentException when no field is named
     * @throws \UnexpectedValueException when `$rows` is no list, a row is no array or a named key is missing
     */
    public function __invoke(array $rows, string ...$fields): array
    {
        if ($fields === []) {
            throw new \InvalidArgumentException('ProjectRows needs at least one field to cut a row to.');
        }
        if (!array_is_list($rows)) {
            throw new \UnexpectedValueException('ProjectRows expects a list of rows, got an array with non-list keys.');
        }

        $projected = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException(sprintf(
                    'Row %d is not an array (%s).',
                    $index,
                    get_debug_type($row)
                ));
            }

            $cut = [];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    throw new \UnexpectedValueException(sprintf('Row %d lacks the field "%s".', $index, $field));
                }
                $cut[$field] = $row[$field];
            }
            $projected[] = $cut;
        }

        return $projected;
    }
}
