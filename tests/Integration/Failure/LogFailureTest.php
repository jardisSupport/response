<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Failure;

use JardisSupport\Contract\Kernel\ReportedFailureExceptionInterface;
use JardisSupport\Response\Handler\Answer\AnswerThrowable;
use JardisSupport\Response\Handler\Failure\LogFailure;
use JardisSupport\Response\Tests\Support\Log\RecordingLogger;
use JardisSupport\Response\Tests\Support\Outcome\AnswerHarness;
use PHPUnit\Framework\TestCase;

/**
 * Logging is its own step: only the owner of the run, only for a 5xx answer, once.
 */
class LogFailureTest extends TestCase
{
    public function testTheOwnerLogsAFiveHundredOnceWithTheProcessName(): void
    {
        $logger = new RecordingLogger();
        $cause = new \RuntimeException('disk full');

        (new LogFailure($logger))($cause, 'PlaceOrder', true, $this->answer($cause));

        $this->assertCount(1, $logger->records);
        $this->assertSame('disk full', $logger->records[0]['message']);
        $this->assertSame($cause, $logger->records[0]['context']['exception']);
        $this->assertSame('PlaceOrder', $logger->records[0]['context']['process']);
    }

    public function testWhoDoesNotOwnTheRunLogsNothing(): void
    {
        $logger = new RecordingLogger();
        $cause = new \RuntimeException('disk full');

        (new LogFailure($logger))($cause, 'PlaceOrder', false, $this->answer($cause));

        $this->assertSame([], $logger->records);
    }

    public function testAFourHundredIsABusinessOutcomeAndNeverLogged(): void
    {
        $logger = new RecordingLogger();
        $cause = new \RuntimeException('not a 5xx on the wire');
        $conflict = (new AnswerThrowable())(
            AnswerHarness::result('X'),
            AnswerHarness::transform(),
            new \JardisSupport\Contract\Repository\Exception\TransientConflictException('deadlock'),
            true,
            'Order'
        );

        (new LogFailure($logger))($cause, 'PlaceOrder', true, $conflict);

        $this->assertSame(409, $conflict->getStatus());
        $this->assertSame([], $logger->records);
    }

    public function testACauseAlreadyReportedWhereItAroseIsNotLoggedAgain(): void
    {
        $logger = new RecordingLogger();
        $cause = new class ('inner door') extends \RuntimeException implements ReportedFailureExceptionInterface {
        };

        (new LogFailure($logger))($cause, 'PlaceOrder', true, $this->answer($cause));

        $this->assertSame([], $logger->records);
    }

    public function testWithoutALoggerOrWithAFailingOneTheAnswerStands(): void
    {
        $cause = new \RuntimeException('x');
        $answer = $this->answer($cause);

        $this->assertNull((new LogFailure())($cause, 'P', true, $answer));
        $this->assertNull((new LogFailure(new RecordingLogger(true)))($cause, 'P', true, $answer));
    }

    private function answer(\Throwable $cause): \JardisSupport\Contract\Kernel\DomainResponseInterface
    {
        return (new AnswerThrowable())(AnswerHarness::result('X'), AnswerHarness::transform(), $cause, true, 'Order');
    }
}
