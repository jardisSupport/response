<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Door;

/**
 * Door step: a use case that needs an actor needs an actor.
 *
 * Answers `false` when the actor is required and missing - the door then answers 401 before any
 * work starts. Whether the actor may do the thing is a decision of the use case (403), never of
 * the door.
 */
final class CheckActor
{
    public const string MESSAGE_KEY = 'process.actorMissing';

    public function __invoke(bool $required, ?object $actor): bool
    {
        return !$required || $actor !== null;
    }
}
