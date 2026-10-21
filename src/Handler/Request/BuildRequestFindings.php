<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Request;

use JardisSupport\Response\Data\InputFindings;
use JardisSupport\Response\Handler\Failure\DescribeFieldFinding;

/**
 * Turns the findings the request readers collected (`field`, `reason`) into the findings of the 400
 * answer ({@see InputFindings} of `jardissupport/response`): each gets its text and its message key.
 *
 * The whole vocabulary - `missing`, `type`, `unknown` and the fallback - is that of `response`
 * ({@see DescribeFieldFinding}); this closure only chains it over the findings.
 *
 * Atomic closure - one entry point.
 */
final class BuildRequestFindings
{
    public const string REASON_MISSING = DescribeFieldFinding::REASON_MISSING;
    public const string REASON_TYPE = DescribeFieldFinding::REASON_TYPE;
    public const string REASON_UNKNOWN = DescribeFieldFinding::REASON_UNKNOWN;

    public function __construct(
        private readonly DescribeFieldFinding $describe = new DescribeFieldFinding(),
    ) {
    }

    /**
     * @param list<array{field: string, reason: string}> $fields
     */
    public function __invoke(array $fields): InputFindings
    {
        return new InputFindings(
            array_map(fn(array $finding): array => ($this->describe)($finding + ['message' => '']), $fields),
            []
        );
    }
}
