<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use JardisSupport\Response\Data\Failure\Forbidden;

/**
 * Builds the 403 failure form from the message key and the context of the denial.
 */
final class ForbiddenPayload
{
    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(string $messageKey, array $context): Forbidden
    {
        return new Forbidden($messageKey, $context);
    }
}
