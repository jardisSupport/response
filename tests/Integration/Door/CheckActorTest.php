<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Door;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Handler\Door\CheckActor;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use PHPUnit\Framework\TestCase;

/**
 * A required actor needs an actor: otherwise the door answers 401 before any work. The door step
 * never answers 403 (A-S4).
 */
class CheckActorTest extends TestCase
{
    public function testMissingActorAnswers401WithoutNodeOrTransaction(): void
    {
        $this->assertFalse((new CheckActor())(true, null));

        $response = (new BuildFailureOutcome())(ResponseStatus::Unauthorized, CheckActor::MESSAGE_KEY);
        $this->assertSame('process.actorMissing', $response->messageKey);
        $this->assertSame([], (new BuildResponseData())(ResponseStatus::Unauthorized, $response, null, 'PlaceOrder'));
    }

    public function testPresentActorRunsTheProcess(): void
    {
        $this->assertTrue((new CheckActor())(true, new \stdClass()));
    }

    public function testWithoutARequiredActorNoActorIsNeeded(): void
    {
        $this->assertTrue((new CheckActor())(false, null));
        $this->assertTrue((new CheckActor())(false, new \stdClass()));
    }

    public function testTheStepNeverAnswers403(): void
    {
        $checkActor = new CheckActor();

        $results = [
            $checkActor(false, null),
            $checkActor(false, new \stdClass()),
            $checkActor(true, new \stdClass()),
            $checkActor(true, null),
        ];

        $this->assertSame([true, true, true, false], $results, 'only a missing required actor says no (401)');
        $this->assertSame('bool', (string) (new \ReflectionMethod(CheckActor::class, '__invoke'))->getReturnType());
    }
}
