<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Answer;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Response\Data\Response\ContextResponse;
use JardisSupport\Response\Handler\Request\BuildRequestFindings;
use JardisSupport\Response\Handler\Response\TransformResponse;
use Psr\Clock\ClockInterface;

/**
 * The one 400 answer of a request whose input does not fit: every collected finding, in the validation
 * form every door answers with (`data = {@type: validation, fields: [{field, reason, message,
 * messageKey}]}`, a list element named with its index). The findings come from the request readers
 * ({@see BuildRequestFindings}), the answer from {@see AnswerFindings} - no
 * envelope of its own; `meta` carries the four fields of every other answer of the domain.
 *
 * Atomic closure - one entry point.
 */
final class AnswerInvalidRequest
{
    public const string CONTEXT = 'validation';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly BuildRequestFindings $buildRequestFindings = new BuildRequestFindings(),
        private readonly AnswerFindings $answerFindings = new AnswerFindings(),
    ) {
    }

    /**
     * @param list<array{field: string, reason: string}> $fields
     * @param string $version the class version this request was served for (meta.version)
     */
    public function __invoke(array $fields, string $version = ''): DomainResponseInterface
    {
        return ($this->answerFindings)(
            new ContextResponse(self::CONTEXT),
            new TransformResponse($this->clock, $version),
            ($this->buildRequestFindings)($fields),
        );
    }
}
