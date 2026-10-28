<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;

/**
 * Cuts the rows of the lists in a 2xx answer down to their outer contract.
 *
 * `$cuts` maps a field of the answer data to the row keys that list keeps. Each named field is cut
 * with {@see ProjectRows} - a row that lacks a named key is drift and fails loudly, as there. A
 * field that is not in the data or is `null` is skipped without error and stays as it is; every
 * other field passes unchanged. Status, errors, metadata and events pass through (see
 * {@see ReshapeAnswer}); every answer that is not 2xx is returned as the very same instance.
 *
 * The closure knows no field name of its own - only the cuts it is given.
 *
 * Atomic closure - one entry point.
 */
final class ProjectAnswerRows
{
    public function __construct(
        private readonly ReshapeAnswer $reshapeAnswer = new ReshapeAnswer(),
        private readonly ProjectRows $projectRows = new ProjectRows(),
    ) {
    }

    /**
     * @param array<string, list<string>> $cuts field of the answer data => row keys the rows of that field keep
     *
     * @throws \InvalidArgumentException when a cut names no row key
     * @throws \UnexpectedValueException when a present field is no list of rows or a row lacks a named key
     */
    public function __invoke(DomainResponseInterface $response, array $cuts): DomainResponseInterface
    {
        return ($this->reshapeAnswer)(
            $response,
            function (array $data) use ($cuts): array {
                foreach ($cuts as $field => $keys) {
                    if (!isset($data[$field])) {
                        continue;
                    }
                    if (!is_array($data[$field])) {
                        throw new \UnexpectedValueException(sprintf(
                            'Field "%s" is not a list of rows (%s).',
                            $field,
                            get_debug_type($data[$field])
                        ));
                    }
                    $data[$field] = ($this->projectRows)($data[$field], ...$keys);
                }

                return $data;
            }
        );
    }
}
