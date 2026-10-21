<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\ContextResponse;
use JardisSupport\Response\Handler\Response\ProjectAnswer;
use JardisSupport\Response\Tests\Support\FakeDomainResponse;
use JardisSupport\Response\Tests\Support\Outcome\AnswerHarness;
use PHPUnit\Framework\TestCase;

/**
 * The outer door cuts an inner answer down to its outer contract.
 */
class ProjectAnswerTest extends TestCase
{
    public function testSuccessKeepsTypeAndTheNamedFieldsOnly(): void
    {
        $inner = $this->innerAnswer(ResponseStatus::Success);

        $outer = (new ProjectAnswer())($inner, 'orderNumber');

        $this->assertSame(['@type' => 'order', 'orderNumber' => 'ORD-1'], $outer->getData());
    }

    public function testFieldOrderFollowsTheData(): void
    {
        $inner = $this->innerAnswer(ResponseStatus::Success);

        $outer = (new ProjectAnswer())($inner, 'orderNumber', 'id');

        $this->assertSame(['@type', 'id', 'orderNumber'], array_keys($outer->getData()));
    }

    public function testMissingFieldIsNotAdded(): void
    {
        $inner = $this->innerAnswer(ResponseStatus::Success);

        $outer = (new ProjectAnswer())($inner, 'orderNumber', 'customerNumber');

        $this->assertSame(['@type' => 'order', 'orderNumber' => 'ORD-1'], $outer->getData());
    }

    public function testEmptyFieldListLeavesOnlyTheType(): void
    {
        $inner = $this->innerAnswer(ResponseStatus::Success);

        $outer = (new ProjectAnswer())($inner);

        $this->assertSame(['@type' => 'order'], $outer->getData());
    }

    public function testAnswerWithoutTypeKeepsOnlyNamedFields(): void
    {
        $inner = new FakeDomainResponse(200, ['id' => 7, 'orderNumber' => 'ORD-1']);

        $outer = (new ProjectAnswer())($inner, 'orderNumber');

        $this->assertSame(['orderNumber' => 'ORD-1'], $outer->getData());
    }

    public function testStatusStaysCreated(): void
    {
        $inner = $this->innerAnswer(ResponseStatus::Created);

        $outer = (new ProjectAnswer())($inner, 'orderNumber');

        $this->assertSame(201, $outer->getStatus());
        $this->assertTrue($outer->isSuccess());
    }

    public function testFailureAnswersComeBackAsTheSameInstance(): void
    {
        foreach ([400, 401, 404, 409, 422, 500] as $status) {
            $inner = new FakeDomainResponse($status, ['@type' => 'x', 'id' => 7], ['ctx' => ['bad']]);

            $this->assertSame($inner, (new ProjectAnswer())($inner, 'orderNumber'), "status $status");
        }
    }

    public function testEventsOfBothScopesSurvive(): void
    {
        $internal = new \stdClass();
        $domain = new \stdClass();
        $inner = $this->innerAnswer(ResponseStatus::Created, [EventScope::Internal->value => $internal, EventScope::Domain->value => $domain]);

        $outer = (new ProjectAnswer())($inner, 'orderNumber');

        $this->assertSame($inner->getEvents(EventScope::Internal), $outer->getEvents(EventScope::Internal));
        $this->assertSame($inner->getEvents(EventScope::Domain), $outer->getEvents(EventScope::Domain));
        $this->assertSame([$internal], $outer->getEvents(EventScope::Internal)['Order']);
        $this->assertSame([$domain], $outer->getEvents(EventScope::Domain)['Order']);
    }

    public function testErrorsAndMetadataSurvive(): void
    {
        $inner = $this->innerAnswer(ResponseStatus::Success, [], 'v2');

        $outer = (new ProjectAnswer())($inner, 'orderNumber');

        $this->assertSame($inner->getErrors(), $outer->getErrors());
        $this->assertSame($inner->getMetadata(), $outer->getMetadata());
        $this->assertSame('v2', $outer->getMetadata()['version']);
    }

    /**
     * An inner answer as a door builds it: type, internal id, public key.
     *
     * @param array<string, object> $eventsByScope scope value => event
     */
    private function innerAnswer(ResponseStatus $status, array $eventsByScope = [], string $version = ''): DomainResponseInterface
    {
        $result = new ContextResponse('Order');
        $result->setData(['@type' => 'order', 'id' => 7, 'orderNumber' => 'ORD-1']);
        foreach ($eventsByScope as $scope => $event) {
            $result->addEvent($event, EventScope::from($scope));
        }

        return AnswerHarness::transform($version)($result, $status);
    }
}
