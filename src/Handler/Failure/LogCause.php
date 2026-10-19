<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Failure;

use Psr\Log\LoggerInterface;

/**
 * Writes the cause of a failed use case to the log.
 *
 * A 5xx answer carries nothing of its throwable, the cause lives only here: the logger gets an
 * `error` (message of the throwable, context `exception` and `process`). Without a logger it does
 * nothing. A logger that fails never replaces the answer.
 *
 * Exactly once per cause is the door's decision: only the owning run calls this, and never for a
 * 4xx (a business outcome, including the 409 of a stale write the caller repeats).
 */
final class LogCause
{
    public function __construct(private readonly ?LoggerInterface $logger)
    {
    }

    public function __invoke(\Throwable $cause, string $process): void
    {
        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->error($cause->getMessage(), ['exception' => $cause, 'process' => $process]);
        } catch (\Throwable) {
            // dropped on purpose - the answer does not depend on the log
        }
    }
}
