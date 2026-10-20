<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Support\Outcome;

use DateTimeImmutable;
use DateTimeZone;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Response\Data\Response\ContextResponse;
use JardisSupport\Response\Handler\Response\TransformResponse;
use JardisSupport\Response\Tests\Support\EnvelopeCases;
use JardisSupport\Response\Tests\Support\EnvelopeJson;
use JardisSupport\Scheduling\Clock\FixedClock;

/**
 * The two things a door hands every answer closure: a fresh context response and the transformer with a
 * frozen clock; plus the bytes of a finished answer.
 */
final class AnswerHarness
{
    public static function result(string $context): ContextResponse
    {
        return new ContextResponse($context);
    }

    public static function transform(string $version = ''): TransformResponse
    {
        $clock = new FixedClock(new DateTimeImmutable(EnvelopeCases::FIXED_AT, new DateTimeZone('UTC')));

        return new TransformResponse($clock, $version);
    }

    public static function bytes(DomainResponseInterface $response): string
    {
        return EnvelopeJson::encode(EnvelopeJson::envelope($response));
    }
}
