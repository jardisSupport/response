<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\DomainResponse;
use JardisSupport\Response\Handler\Response\ProjectAnswerRows;
use JardisSupport\Response\Tests\Support\FakeDomainResponse;
use PHPUnit\Framework\TestCase;

/**
 * The rows of the lists in a 2xx answer are cut down to the outer contract of each list.
 */
class ProjectAnswerRowsTest extends TestCase
{
    public function testCutsTheRowsOfTheNamedFieldAndLeavesOtherFieldsAlone(): void
    {
        $inner = $this->answer([
            '@type' => 'overview',
            'items' => [
                ['id' => 1, 'code' => 'A', 'note' => 'x'],
                ['id' => 2, 'code' => 'B', 'note' => 'y'],
            ],
            'total' => 2,
        ]);

        $outer = (new ProjectAnswerRows())($inner, ['items' => ['code', 'note']]);

        $this->assertSame([
            '@type' => 'overview',
            'items' => [['code' => 'A', 'note' => 'x'], ['code' => 'B', 'note' => 'y']],
            'total' => 2,
        ], $outer->getData());
    }

    public function testCutsSeveralFields(): void
    {
        $inner = $this->answer([
            'first' => [['id' => 1, 'a' => 'x']],
            'second' => [['id' => 2, 'b' => 'y']],
        ]);

        $outer = (new ProjectAnswerRows())($inner, ['first' => ['a'], 'second' => ['b']]);

        $this->assertSame(['first' => [['a' => 'x']], 'second' => [['b' => 'y']]], $outer->getData());
    }

    public function testMissingRowKeyFailsLoudly(): void
    {
        $inner = $this->answer(['items' => [['id' => 1, 'code' => 'A']]]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Row 0 lacks the field "note"');

        (new ProjectAnswerRows())($inner, ['items' => ['code', 'note']]);
    }

    public function testFieldThatIsNotInTheDataIsSkippedWithoutError(): void
    {
        $inner = $this->answer(['items' => [['id' => 1, 'code' => 'A']]]);

        $outer = (new ProjectAnswerRows())($inner, ['items' => ['code'], 'absent' => ['x']]);

        $this->assertSame(['items' => [['code' => 'A']]], $outer->getData());
        $this->assertArrayNotHasKey('absent', $outer->getData());
    }

    public function testNullFieldIsSkippedWithoutErrorAndStaysNull(): void
    {
        $inner = $this->answer(['items' => null, 'other' => [['id' => 1, 'k' => 'v']]]);

        $outer = (new ProjectAnswerRows())($inner, ['items' => ['code'], 'other' => ['k']]);

        $this->assertSame(['items' => null, 'other' => [['k' => 'v']]], $outer->getData());
    }

    public function testFieldThatIsNoListOfRowsFailsLoudly(): void
    {
        $inner = $this->answer(['items' => 'not rows']);

        $this->expectException(\UnexpectedValueException::class);

        (new ProjectAnswerRows())($inner, ['items' => ['code']]);
    }

    public function testStatusEventsErrorsAndMetadataAreCarriedOver(): void
    {
        $event = new \stdClass();
        $inner = new DomainResponse(
            ResponseStatus::Created,
            ['items' => [['id' => 1, 'code' => 'A']]],
            [],
            ['ctx' => ['a warning']],
            ['version' => 'v2'],
            [EventScope::Domain->value => ['Unit' => [$event]]]
        );

        $outer = (new ProjectAnswerRows())($inner, ['items' => ['code']]);

        $this->assertSame(201, $outer->getStatus());
        $this->assertSame([$event], $outer->getEvents(EventScope::Domain)['Unit']);
        $this->assertSame(['ctx' => ['a warning']], $outer->getErrors());
        $this->assertSame(['version' => 'v2'], $outer->getMetadata());
    }

    public function testAnswersThatAreNot2xxComeBackAsTheSameInstance(): void
    {
        foreach ([400, 404, 409, 422, 500] as $status) {
            $inner = new FakeDomainResponse($status, ['items' => [['id' => 1]]], ['ctx' => ['bad']]);

            $this->assertSame($inner, (new ProjectAnswerRows())($inner, ['items' => ['code']]), "status $status");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function answer(array $data): DomainResponse
    {
        return new DomainResponse(ResponseStatus::Success, $data, [], [], []);
    }
}
