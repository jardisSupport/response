<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\DomainResponse;
use JardisSupport\Response\Handler\Response\ReshapeAnswer;
use JardisSupport\Response\Tests\Support\FakeDomainResponse;
use PHPUnit\Framework\TestCase;

/**
 * The data of a 2xx answer is reshaped by the step of the caller; everything else is carried over,
 * every other answer comes back as the very same instance.
 */
class ReshapeAnswerTest extends TestCase
{
    public function testSuccessCarriesTheDataTheStepReturns(): void
    {
        $inner = new DomainResponse(ResponseStatus::Success, ['@type' => 'item', 'name' => 'a', 'extra' => 1], [], [], []);

        $outer = (new ReshapeAnswer())(
            $inner,
            static fn (array $data): array => ['@type' => $data['@type'], 'label' => strtoupper($data['name'])]
        );

        $this->assertNotSame($inner, $outer);
        $this->assertSame(['@type' => 'item', 'label' => 'A'], $outer->getData());
    }

    public function testStepReceivesTheDataOfTheAnswer(): void
    {
        $inner = new DomainResponse(ResponseStatus::Success, ['k' => 'v'], [], [], []);
        $received = null;

        (new ReshapeAnswer())($inner, static function (array $data) use (&$received): array {
            $received = $data;

            return $data;
        });

        $this->assertSame(['k' => 'v'], $received);
    }

    public function testStatusStaysCreated(): void
    {
        $inner = new DomainResponse(ResponseStatus::Created, ['a' => 1], [], [], []);

        $outer = (new ReshapeAnswer())($inner, static fn (array $data): array => []);

        $this->assertSame(201, $outer->getStatus());
        $this->assertTrue($outer->isSuccess());
        $this->assertSame([], $outer->getData());
    }

    public function testEventsOfBothScopesErrorsAndMetadataAreCarriedOver(): void
    {
        $internal = new \stdClass();
        $domain = new \stdClass();
        $inner = new DomainResponse(
            ResponseStatus::Success,
            ['a' => 1],
            [],
            ['ctx' => ['a warning']],
            ['version' => 'v2'],
            [
                EventScope::Internal->value => ['Unit' => [$internal]],
                EventScope::Domain->value => ['Unit' => [$domain]],
            ]
        );

        $outer = (new ReshapeAnswer())($inner, static fn (array $data): array => ['b' => 2]);

        $this->assertSame(['b' => 2], $outer->getData());
        $this->assertSame([$internal], $outer->getEvents(EventScope::Internal)['Unit']);
        $this->assertSame([$domain], $outer->getEvents(EventScope::Domain)['Unit']);
        $this->assertSame($inner->getEvents(), $outer->getEvents());
        $this->assertSame(['ctx' => ['a warning']], $outer->getErrors());
        $this->assertSame(['version' => 'v2'], $outer->getMetadata());
    }

    public function testAnswersThatAreNot2xxComeBackAsTheSameInstanceAndTheStepIsNotCalled(): void
    {
        foreach ([400, 401, 404, 409, 422, 500] as $status) {
            $inner = new FakeDomainResponse($status, ['id' => 7], ['ctx' => ['bad']]);
            $called = false;

            $outer = (new ReshapeAnswer())($inner, static function (array $data) use (&$called): array {
                $called = true;

                return $data;
            });

            $this->assertSame($inner, $outer, "status $status");
            $this->assertFalse($called, "status $status");
        }
    }
}
