<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Failure;

use JardisSupport\Contract\Repository\Exception\TransientConflictException;
use JardisSupport\Response\Handler\Failure\LogCause;
use JardisSupport\Response\Handler\Failure\MapThrowable;
use JardisSupport\Response\Tests\Support\Log\RecordingLogger;
use PHPUnit\Framework\TestCase;

/**
 * The cause of a 5xx answer reaches the logger exactly once; which run logs it is the door's call.
 */
class LogCauseTest extends TestCase
{
    public function testA500LogsItsCauseOnceWithProcessName(): void
    {
        $logger = new RecordingLogger();
        $failure = new \RuntimeException('disk full');

        (new LogCause($logger))($failure, 'PlaceOrder');

        $this->assertCount(1, $logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
        $this->assertSame('disk full', $logger->records[0]['message']);
        $this->assertSame($failure, $logger->records[0]['context']['exception']);
        $this->assertSame('PlaceOrder', $logger->records[0]['context']['process']);
    }

    public function testWithoutALoggerALogCallDoesNothing(): void
    {
        $this->assertNull((new LogCause(null))(new \RuntimeException('x'), 'PlaceOrder'));
    }

    public function testTheStepTakesNoStatusSoTheDoorDecidesWhatIsLogged(): void
    {
        $parameters = array_map(
            static fn(\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod(LogCause::class, '__invoke'))->getParameters()
        );

        $this->assertSame(['cause', 'process'], $parameters);
    }

    public function testAStaleWriteCauseIsLoggedAsGivenBecauseTheDoorFiltersBeforeTheCall(): void
    {
        $logger = new RecordingLogger();
        $conflict = new TransientConflictException('deadlock', '40001');

        (new LogCause($logger))($conflict, 'PlaceOrder');

        $this->assertCount(1, $logger->records);
        $this->assertSame($conflict, $logger->records[0]['context']['exception']);
    }

    public function testAFailureOfANestedRunIsLoggedOnceByTheOwner(): void
    {
        $logger = new RecordingLogger();
        $failure = new \RuntimeException('inner broke');
        $caught = null;

        try {
            (new MapThrowable())($failure, false, 'Inner');
        } catch (\Throwable $thrown) {
            $caught = $thrown;
        }
        $this->assertSame($failure, $caught, 'the nested run hands the same throwable up and does not log');
        $this->assertSame([], $logger->records);

        (new LogCause($logger))($caught, 'Outer');

        $this->assertCount(1, $logger->records);
        $this->assertSame('Outer', $logger->records[0]['context']['process']);
        $this->assertSame($failure, $logger->records[0]['context']['exception']);
    }

    public function testAFailingLoggerNeverBreaksTheCaller(): void
    {
        $this->assertNull((new LogCause(new RecordingLogger(failing: true)))(new \LogicException('real cause'), 'PlaceOrder'));
    }
}
