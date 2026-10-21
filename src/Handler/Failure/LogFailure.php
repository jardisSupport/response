<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use Psr\Log\LoggerInterface;

/**
 * Logs the cause of a failed use case - only when the caller owns the run and the answer is a 5xx.
 *
 * The decision "who logs, and when" is this step's, kept out of the answer closures: an answer maps, this
 * step writes. A 4xx is a business outcome and never logged; a caller that does not own the run throws the
 * cause on to its owner, who logs it once. The write itself is {@see LogCause}.
 *
 * Atomic closure - one entry point.
 */
final class LogFailure
{
    private readonly LogCause $logCause;

    public function __construct(?LoggerInterface $logger = null, ?LogCause $logCause = null)
    {
        $this->logCause = $logCause ?? new LogCause($logger);
    }

    /**
     * @param bool $owner true when no transaction was open on entry
     * @param DomainResponseInterface $answer the answer that was built from the cause
     */
    public function __invoke(\Throwable $cause, string $process, bool $owner, DomainResponseInterface $answer): void
    {
        if ($owner && $answer->getStatus() >= ResponseStatus::InternalError->value) {
            ($this->logCause)($cause, $process);
        }
    }
}
