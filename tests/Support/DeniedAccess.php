<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support;

use JardisSupport\Contract\Kernel\AccessDeniedExceptionInterface;

/**
 * Test double of a generated access denial: key and context are data of the exception.
 */
final class DeniedAccess extends \RuntimeException implements AccessDeniedExceptionInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(private readonly string $messageKey, private readonly array $context = [])
    {
        parent::__construct('access denied: ' . $messageKey);
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
