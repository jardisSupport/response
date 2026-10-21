<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Answer;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Response\Data\Reference;
use JardisSupport\Response\Handler\Answer\AnswerCommand;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Tests\Support\EnvelopeJson;
use JardisSupport\Response\Tests\Support\Outcome\AnswerHarness;
use PHPUnit\Framework\TestCase;

/**
 * The bytes of the write-side reference envelope, pinned.
 *
 * The pinned shapes are the answers a generated aggregate command hands out today by hand
 * (`@type` plus the business key; with children: one child as its identifier, a child list as a list).
 */
class ReferenceEnvelopeTest extends TestCase
{
    public function testUpdateAnswerIsTypePlusBusinessKeyAsPinnedBytes(): void
    {
        $answer = (new AnswerCommand())(
            AnswerHarness::result('UpdateOrder'),
            AnswerHarness::transform(),
            new Reference('ORD-2026-0001', [], 'orderNumber'),
            'Order'
        );

        $this->assertSame(
            <<<'JSON'
            {
                "status": 200,
                "data": {
                    "@type": "order",
                    "orderNumber": "ORD-2026-0001"
                },
                "errors": {},
                "meta": {
                    "duration": 0.0,
                    "contexts": [
                        "UpdateOrder"
                    ],
                    "timestamp": "2026-01-15T10:30:00+00:00",
                    "version": ""
                }
            }
            JSON,
            AnswerHarness::bytes($answer)
        );
    }

    public function testCreateAnswerCarriesTheChildReferencesUnderTheirKeys(): void
    {
        $reference = new Reference(
            'ORD-2026-0002',
            ['customerIdentifier' => 'CUST-9', 'orderItemIdentifiers' => ['I-1', 'I-2'], 'noteIdentifier' => null],
            'orderNumber'
        );

        $data = (new BuildResponseData())(ResponseStatus::Success, $reference, null, 'Order');

        $this->assertSame(
            <<<'JSON'
            {
                "@type": "order",
                "orderNumber": "ORD-2026-0002",
                "customerIdentifier": "CUST-9",
                "orderItemIdentifiers": [
                    "I-1",
                    "I-2"
                ],
                "noteIdentifier": null
            }
            JSON,
            EnvelopeJson::encode($data)
        );
    }

    public function testWithoutANamedIdentifierTheRootAnswersAsId(): void
    {
        $reference = new Reference(17);

        $this->assertSame('id', $reference->identifier());
        $this->assertSame(
            ['@type' => 'order', 'id' => 17],
            (new BuildResponseData())(ResponseStatus::Success, $reference, null, 'Order')
        );
    }

    public function testAReferenceOnAFailureStatusIsNotAnAnswerAndAddsNothing(): void
    {
        $this->assertSame(
            [],
            (new BuildResponseData())(ResponseStatus::NotFound, new Reference('ORD-1', [], 'orderNumber'), null, 'Order')
        );
    }
}
