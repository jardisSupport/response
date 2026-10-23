<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Response;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Contract\Repository\Exception\TransientConflictException;
use JardisSupport\Response\Data\Failure\FailureForm;
use JardisSupport\Response\Data\Failure\ValidationFailure;
use JardisSupport\Response\Exception\ConfigurationException;
use JardisSupport\Response\Handler\Answer\AnswerUnauthorized;
use JardisSupport\Response\Handler\Door\ValidateInput;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Failure\MapThrowable;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Tests\Support\Order\OrderInput;
use JardisSupport\Response\Tests\Support\Order\OrderValidation;
use PHPUnit\Framework\TestCase;

/**
 * The failures of the package's own door steps (input, thrown) never put their message key
 * on the wire: the key stays on the form / response object, `data` carries the form alone or
 * nothing.
 */
class LayerFailuresOnTheWireTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function data(ResponseStatus $status, FailureForm|\stdClass $response): array
    {
        return (new BuildResponseData())($status, $response, null, 'P');
    }

    public function testMissingActorAnswers401WithEmptyData(): void
    {
        $response = (new BuildFailureOutcome())(ResponseStatus::Unauthorized, AnswerUnauthorized::MESSAGE_KEY);

        $this->assertSame('process.actorMissing', $response->messageKey, 'the key stays internal');
        $this->assertSame([], $this->data(ResponseStatus::Unauthorized, $response));
        $this->assertSame('{}', json_encode((object) $this->data(ResponseStatus::Unauthorized, $response)));
    }

    public function testInvalidInputAnswers400WithTheFormAlone(): void
    {
        $findings = (new ValidateInput(OrderValidation::validator()))(new OrderInput(null, []));
        $form = (new BuildFailureOutcome())(
            ResponseStatus::ValidationError,
            ValidateInput::MESSAGE_KEY,
            ['fields' => $findings->fields],
            $findings->errors
        );

        $this->assertInstanceOf(ValidationFailure::class, $form);
        $this->assertSame(['@type', 'fields'], array_keys($this->data(ResponseStatus::ValidationError, $form)));
    }

    public function testTransientConflictAnswers409WithTheFormAlone(): void
    {
        $mapped = (new MapThrowable())(new TransientConflictException('deadlock', '40001'), true, 'P');

        $this->assertSame(ResponseStatus::Conflict, $mapped->status);
        $this->assertSame(
            ['@type', 'reason', 'aggregate', 'context', 'violated'],
            array_keys($this->data($mapped->status, $mapped->response))
        );
    }

    public function testLoopLimitAnswers500WithoutData(): void
    {
        $mapped = (new MapThrowable())(new \OverflowException('loop limit 2'), true, 'P');

        $this->assertSame(ResponseStatus::InternalError, $mapped->status);
        $this->assertSame([], $this->data($mapped->status, $mapped->response));
    }

    public function testMissingConnectionAnswers500WithoutData(): void
    {
        $mapped = (new MapThrowable())(new ConfigurationException('no connection'), true, 'P');

        $this->assertSame(ResponseStatus::InternalError, $mapped->status);
        $this->assertSame([], $this->data($mapped->status, $mapped->response));
    }
}
