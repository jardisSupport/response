<?php

declare(strict_types=1);

namespace JardisSupport\Response\Data;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Failure\FailureForm;

/**
 * The answer a thrown failure maps to: its status and its response (a failure form, or an empty
 * object for a 5xx).
 *
 * Result of {@see \JardisSupport\Response\Handler\Failure\MapThrowable}.
 */
final readonly class MappedFailure
{
    public function __construct(
        public ResponseStatus $status,
        public FailureForm|\stdClass $response,
    ) {
    }
}
