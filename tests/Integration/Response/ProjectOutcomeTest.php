<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\DomainResponseInterface;
use JardisSupport\Contract\Kernel\EventScope;
use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Response\ContextResponse;
use JardisSupport\Response\Data\Response\OutcomeForm;
use JardisSupport\Response\Exception\ConfigurationException;
use JardisSupport\Response\Handler\Response\ProjectOutcome;
use JardisSupport\Response\Tests\Support\FakeDomainResponse;
use JardisSupport\Response\Tests\Support\Outcome\AnswerHarness;
use PHPUnit\Framework\TestCase;

/**
 * The outer door keeps, per outcome of a process, exactly the fields of the outcome that ran.
 */
class ProjectOutcomeTest extends TestCase
{
    public function testDataKeepsTypeOutcomeAndTheFieldsOfTheFormInFormOrderByteExact(): void
    {
        $inner = $this->answer([
            '@type' => 'sampleProcess',
            '@outcome' => 'accepted',
            'reference' => 'REF-1',
            'items' => ['I-1'],
            'reason' => 'internal',
        ]);

        $outer = (new ProjectOutcome())($inner, $this->forms());

        $this->assertSame(
            '{"@type":"sampleProcess","@outcome":"accepted","items":["I-1"],"reference":"REF-1"}',
            json_encode($outer->getData(), JSON_THROW_ON_ERROR)
        );
    }

    public function testAFieldOfAnotherFormIsGone(): void
    {
        $inner = $this->answer([
            '@type' => 'sampleProcess',
            '@outcome' => 'deferred',
            'reason' => 'timeout',
            'reference' => 'REF-1',
            'items' => ['I-1'],
        ]);

        $outer = (new ProjectOutcome())($inner, $this->forms());

        $this->assertSame(
            ['@type' => 'sampleProcess', '@outcome' => 'deferred', 'reason' => 'timeout'],
            $outer->getData()
        );
    }

    public function testUnknownOutcomeIsAConfigurationFault(): void
    {
        $inner = $this->answer(['@type' => 'sampleProcess', '@outcome' => 'vanished']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('vanished');

        (new ProjectOutcome())($inner, $this->forms());
    }

    public function testMissingOutcomeIsAConfigurationFault(): void
    {
        $inner = $this->answer(['@type' => 'sampleProcess', 'reason' => 'timeout']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('@outcome');

        (new ProjectOutcome())($inner, $this->forms());
    }

    public function testFailureAnswersComeBackAsTheSameInstanceWithoutAnOutcome(): void
    {
        foreach ([400, 401, 404, 409, 422, 500] as $status) {
            $inner = new FakeDomainResponse($status, ['@type' => 'x', 'fields' => []], ['ctx' => ['bad']]);

            $this->assertSame($inner, (new ProjectOutcome())($inner, $this->forms()), "status $status");
        }
    }

    public function testFailureBytesAreTheSameAsBeforeThePackageStep(): void
    {
        $result = AnswerHarness::result('SampleProcess');
        $result->setData(['@type' => 'ruleViolation', 'rule' => 'r', 'messageKey' => 'm', 'context' => []]);
        $inner = AnswerHarness::transform()($result, ResponseStatus::RuleViolation);

        $outer = (new ProjectOutcome())($inner, $this->forms());

        $this->assertSame(AnswerHarness::bytes($inner), AnswerHarness::bytes($outer));
    }

    public function testRowCutsApplyPerOutcome(): void
    {
        $forms = [
            'listed' => new OutcomeForm(['items'], ['items' => ['code']]),
            'detailed' => new OutcomeForm(['items'], ['items' => ['code', 'note']]),
        ];
        $rows = [['id' => 1, 'code' => 'A', 'note' => 'x'], ['id' => 2, 'code' => 'B', 'note' => 'y']];

        $listed = (new ProjectOutcome())($this->answer(['@type' => 't', '@outcome' => 'listed', 'items' => $rows]), $forms);
        $detailed = (new ProjectOutcome())($this->answer(['@type' => 't', '@outcome' => 'detailed', 'items' => $rows]), $forms);

        $this->assertSame([['code' => 'A'], ['code' => 'B']], $listed->getData()['items']);
        $this->assertSame(
            [['code' => 'A', 'note' => 'x'], ['code' => 'B', 'note' => 'y']],
            $detailed->getData()['items']
        );
    }

    public function testAMissingRequiredFieldStaysMissing(): void
    {
        $inner = $this->answer(['@type' => 'sampleProcess', '@outcome' => 'accepted', 'items' => ['I-1']]);

        $outer = (new ProjectOutcome())($inner, $this->forms());

        $this->assertSame(
            ['@type' => 'sampleProcess', '@outcome' => 'accepted', 'items' => ['I-1']],
            $outer->getData()
        );
        $this->assertArrayNotHasKey('reference', $outer->getData());
    }

    public function testStatusErrorsMetadataAndEventsSurvive(): void
    {
        $event = new \stdClass();
        $result = AnswerHarness::result('SampleProcess');
        $result->setData(['@type' => 'sampleProcess', '@outcome' => 'deferred', 'reason' => 'r']);
        $result->addEvent($event, EventScope::Domain);
        $inner = AnswerHarness::transform('v2')($result, ResponseStatus::Created);

        $outer = (new ProjectOutcome())($inner, $this->forms());

        $this->assertSame(201, $outer->getStatus());
        $this->assertSame($inner->getErrors(), $outer->getErrors());
        $this->assertSame($inner->getMetadata(), $outer->getMetadata());
        $this->assertSame($inner->getEvents(EventScope::Domain), $outer->getEvents(EventScope::Domain));
    }

    /**
     * @return array<string, OutcomeForm>
     */
    private function forms(): array
    {
        return [
            'accepted' => new OutcomeForm(['items', 'reference']),
            'deferred' => new OutcomeForm(['reason']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function answer(array $data): DomainResponseInterface
    {
        $result = new ContextResponse('SampleProcess');
        $result->setData($data);

        return AnswerHarness::transform()($result, ResponseStatus::Success);
    }
}
