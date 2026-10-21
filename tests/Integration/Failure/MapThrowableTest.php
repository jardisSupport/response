<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Failure;

use JardisSupport\Contract\Kernel\AccessDeniedExceptionInterface;
use JardisSupport\Contract\Kernel\ConcurrencyConflictExceptionInterface;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Contract\Kernel\ValidationExceptionInterface;
use JardisSupport\Contract\Repository\Exception\TransientConflictException;
use JardisSupport\Response\Data\Failure\Conflict;
use JardisSupport\Response\Data\Failure\Forbidden;
use JardisSupport\Response\Data\Failure\ValidationFailure;
use JardisSupport\Response\Data\MappedFailure;
use JardisSupport\Response\Exception\ConfigurationException;
use JardisSupport\Response\Handler\Failure\MapThrowable;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use PHPUnit\Framework\TestCase;

/**
 * MapThrowable is the one table from a thrown failure to the answer: one test per row (W11), the
 * owner rule (a nested run throws only the stale write and 5xx causes on) and both markers.
 */
class MapThrowableTest extends TestCase
{
    private const FIELDS = [
        ['field' => 'email', 'reason' => 'missing', 'message' => 'Field is required.', 'messageKey' => 'validation.missing'],
        ['field' => 'age', 'reason' => 'invalid', 'message' => 'Must be 18 or older.', 'messageKey' => 'validation.invalid'],
    ];

    private function assertFailsWith500(MappedFailure $mapped): void
    {
        $this->assertSame(ResponseStatus::InternalError, $mapped->status);
        $this->assertEquals(new \stdClass(), $mapped->response, 'no form, an empty object');
    }

    private function thrownBy(\Closure $call): \Throwable
    {
        try {
            $call();
        } catch (\Throwable $thrown) {
            return $thrown;
        }

        $this->fail('expected the failure to be thrown on');
    }

    private function conflictMarker(): ConcurrencyConflictExceptionInterface
    {
        return new class ('write lost') extends \RuntimeException implements ConcurrencyConflictExceptionInterface {
            public function getReason(): string
            {
                return 'version_mismatch';
            }

            public function getAggregate(): string
            {
                return 'Invoice';
            }

            public function getContext(): array
            {
                return ['expected' => 3, 'actual' => 4];
            }

            public function getViolated(): array
            {
                return ['uq_invoice_number'];
            }
        };
    }

    private function validationMarker(): ValidationExceptionInterface
    {
        return new class ('fields invalid') extends \RuntimeException implements ValidationExceptionInterface {
            public function getFields(): array
            {
                return [
                    ['field' => 'email', 'reason' => 'missing', 'message' => 'Field is required.', 'messageKey' => 'validation.missing'],
                    ['field' => 'age', 'reason' => 'invalid', 'message' => 'Must be 18 or older.', 'messageKey' => 'validation.invalid'],
                ];
            }
        };
    }

    private function accessDenied(): AccessDeniedExceptionInterface
    {
        return new class ('denied') extends \RuntimeException implements AccessDeniedExceptionInterface {
            public function getMessageKey(): string
            {
                return 'test.denied';
            }

            public function getContext(): array
            {
                return ['right' => 'a.b:c'];
            }
        };
    }

    public function testConstructionExceptionAnswers500(): void
    {
        $this->assertFailsWith500((new MapThrowable())(new \RuntimeException('bad'), true, 'P'));
    }

    public function testConfigurationExceptionAnswers500(): void
    {
        $this->assertFailsWith500((new MapThrowable())(new ConfigurationException('no auth'), true, 'P'));
    }

    public function testLoopLimitExceededAnswers500(): void
    {
        $this->assertFailsWith500((new MapThrowable())(new \OverflowException('loop limit 2'), true, 'P'));
    }

    public function testUnroutedStatusAnswers500(): void
    {
        $this->assertFailsWith500((new MapThrowable())(new \UnexpectedValueException('unrouted'), true, 'P'));
    }

    public function testAnyOtherThrowableAnswers500(): void
    {
        $this->assertFailsWith500((new MapThrowable())(new \DomainException('anything'), true, 'P'));
    }

    public function testAnErrorIsMappedLikeAnyOtherThrowable(): void
    {
        $this->assertFailsWith500((new MapThrowable())(new \TypeError('wrong type'), true, 'P'));
    }

    public function testNestedRunWithoutSavepointSupportIsAConfigurationError(): void
    {
        $failure = new ConfigurationException('the connection offers no savepoints');

        $thrown = $this->thrownBy(static fn() => (new MapThrowable())($failure, false, 'Inner'));

        $this->assertSame($failure, $thrown, 'a nested run hands a 5xx cause up unchanged');
    }

    public function testTopLevelRunInAnOpenTransactionWithoutSavepointSupportIsAConfigurationError(): void
    {
        $mapped = (new MapThrowable())(new ConfigurationException('the connection offers no savepoints'), true, 'P');

        $this->assertFailsWith500($mapped);
    }

    public function testTransientConflictInTheOwningRunAnswers409(): void
    {
        $mapped = (new MapThrowable())(new TransientConflictException('deadlock', '40001'), true, 'P');

        $this->assertSame(ResponseStatus::Conflict, $mapped->status);
        $form = $mapped->response;
        $this->assertInstanceOf(Conflict::class, $form);
        $this->assertSame('process.transientConflict', $form->messageKey());
        $this->assertSame('stale_write', $form->reason());
        $this->assertSame('P', $form->aggregate(), 'the process name, as the pre-move handler answered');
        $this->assertEquals(
            ['@type' => 'concurrencyConflict', 'reason' => 'stale_write', 'aggregate' => 'P', 'context' => new \stdClass(), 'violated' => []],
            (new BuildResponseData())($mapped->status, $form, null, 'P')
        );
    }

    public function testNestedTransientConflictIsThrownOnAndTheOwningRunAnswers409(): void
    {
        $conflict = new TransientConflictException('snapshot', '1020');

        $thrown = $this->thrownBy(static fn() => (new MapThrowable())($conflict, false, 'Inner'));
        $this->assertSame($conflict, $thrown, 'the very exception of the inner run, unchanged');

        $mapped = (new MapThrowable())($thrown, true, 'Outer');
        $this->assertSame(ResponseStatus::Conflict, $mapped->status);
        $this->assertInstanceOf(Conflict::class, $mapped->response);
        $this->assertSame('Outer', $mapped->response->aggregate());
    }

    public function testNestedRunThrowsTheConflictToItsCaller(): void
    {
        $this->expectException(TransientConflictException::class);

        (new MapThrowable())(new TransientConflictException('x'), false, 'Inner');
    }

    public function testMissingRequiredResponseFieldAnswers500(): void
    {
        $mapped = (new MapThrowable())(new \LogicException('response field "quantity" is missing'), true, 'Checkout');

        $this->assertFailsWith500($mapped);
        $this->assertSame([], get_object_vars($mapped->response), 'nothing of the throwable reaches the answer');
    }

    public function testNestedRunThrowsOnlyTransientAndServerErrorsOn(): void
    {
        $mapThrowable = new MapThrowable();

        foreach ([
            new TransientConflictException('x'),
            new \RuntimeException('5xx cause'),
            new \TypeError('an error'),
            new ConfigurationException('wiring'),
        ] as $failure) {
            $thrown = $this->thrownBy(static fn() => $mapThrowable($failure, false, 'Inner'));
            $this->assertSame($failure, $thrown, $failure::class . ' is thrown on unchanged');
        }
    }

    public function testNestedRunMapsEveryFourXxMarker(): void
    {
        $mapThrowable = new MapThrowable();

        $conflict = $mapThrowable($this->conflictMarker(), false, 'Inner');
        $validation = $mapThrowable($this->validationMarker(), false, 'Inner');

        $this->assertSame(ResponseStatus::Conflict, $conflict->status);
        $this->assertInstanceOf(Conflict::class, $conflict->response);
        $this->assertSame(ResponseStatus::ValidationError, $validation->status);
        $this->assertInstanceOf(ValidationFailure::class, $validation->response);
    }

    public function testConcurrencyConflictMarkerAnswers409WithItsFields(): void
    {
        $mapped = (new MapThrowable())($this->conflictMarker(), true, 'CreateInvoice');

        $this->assertSame(ResponseStatus::Conflict, $mapped->status);
        $form = $mapped->response;
        $this->assertInstanceOf(Conflict::class, $form);
        $this->assertSame('concurrencyConflict', $form->messageKey());
        $this->assertSame('version_mismatch', $form->reason());
        $this->assertSame('Invoice', $form->aggregate(), 'the aggregate the marker names, not the process');
        $this->assertSame(['expected' => 3, 'actual' => 4], $form->context());
        $this->assertSame(['uq_invoice_number'], $form->violated());
    }

    public function testValidationFailedMarkerAnswers400WithItsFields(): void
    {
        $mapped = (new MapThrowable())($this->validationMarker(), true, 'CreateInvoice');

        $this->assertSame(ResponseStatus::ValidationError, $mapped->status);
        $form = $mapped->response;
        $this->assertInstanceOf(ValidationFailure::class, $form);
        $this->assertSame('validation', $form->messageKey());
        $this->assertSame(self::FIELDS, $form->fields());
    }

    public function testAccessDeniedMarkerAnswers403WithKeyAndContextOfTheException(): void
    {
        $mapped = (new MapThrowable())($this->accessDenied(), true, 'CreateInvoice');

        $this->assertSame(ResponseStatus::Forbidden, $mapped->status);
        $this->assertEquals(new Forbidden('test.denied', ['right' => 'a.b:c']), $mapped->response);
        $this->assertSame(
            ['@type' => 'forbidden', 'context' => ['right' => 'a.b:c'], 'messageKey' => 'test.denied'],
            (new BuildResponseData())($mapped->status, $mapped->response, null, 'CreateInvoice')
        );
    }

    public function testAccessDeniedMarkerIsMappedAlsoForANonOwnerAndNeverThrown(): void
    {
        $mapped = (new MapThrowable())($this->accessDenied(), false, 'Inner');

        $this->assertSame(ResponseStatus::Forbidden, $mapped->status);
        $this->assertInstanceOf(Forbidden::class, $mapped->response);
    }
}
