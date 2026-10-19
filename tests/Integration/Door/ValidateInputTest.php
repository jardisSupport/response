<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Door;

use JardisSupport\Contract\Kernel\ResponseStatus;
use JardisSupport\Contract\Validation\ValidationResult;
use JardisSupport\Contract\Validation\ValidatorInterface;
use JardisSupport\Response\Data\Failure\ValidationFailure;
use JardisSupport\Response\Data\InputFindings;
use JardisSupport\Response\Handler\Door\ValidateInput;
use JardisSupport\Response\Handler\Failure\BuildFailureOutcome;
use JardisSupport\Response\Handler\Response\BuildResponseData;
use JardisSupport\Response\Tests\Support\Order\OrderInput;
use JardisSupport\Response\Tests\Support\Order\OrderLine;
use JardisSupport\Response\Tests\Support\Order\OrderValidation;
use JardisSupport\Validation\ObjectValidator;
use JardisSupport\Validation\ValidatorRegistry;
use PHPUnit\Framework\TestCase;

/**
 * The door step validates the input with the injected validator port (real ObjectValidator)
 * before any node, context or transaction exists (A-S2, A-S4, A-V5). Valid <=> `fields === []`.
 */
class ValidateInputTest extends TestCase
{
    public function testInvalidInputAnswers400WithFieldPathsAndTouchesNothing(): void
    {
        $input = new OrderInput(null, [new OrderLine('A-1', 2), new OrderLine('B-7', 0)]);

        $findings = (new ValidateInput(OrderValidation::validator()))($input);

        $this->assertNotSame([], $findings->fields);
        $fields = array_column($findings->fields, null, 'field');
        $this->assertSame(['customer', 'items[1].qty'], array_keys($fields));
        $this->assertSame('missing', $fields['customer']['reason']);
        $this->assertSame('invalid', $fields['items[1].qty']['reason']);
        $this->assertNotSame('', $fields['items[1].qty']['message']);
        $this->assertContains($fields['items[1].qty']['message'], $findings->errors);

        // The door builds the 400 from the findings: paths below the input root (not prefixed
        // again), the key of the package's own step, and no declared key in the data.
        $form = (new BuildFailureOutcome())(
            ResponseStatus::ValidationError,
            ValidateInput::MESSAGE_KEY,
            ['fields' => $findings->fields],
            $findings->errors
        );
        $this->assertInstanceOf(ValidationFailure::class, $form);
        $this->assertSame('process.inputInvalid', $form->messageKey());
        $data = (new BuildResponseData())(ResponseStatus::ValidationError, $form, null, 'PlaceOrder');
        $this->assertSame(['@type', 'fields'], array_keys($data));
        $this->assertSame(['customer', 'items[1].qty'], array_column($data['fields'], 'field'));
    }

    public function testValidInputRunsTheProcess(): void
    {
        $findings = (new ValidateInput(OrderValidation::validator()))(new OrderInput('Ada', []));

        $this->assertInstanceOf(InputFindings::class, $findings);
        $this->assertSame([], $findings->fields, 'valid <=> fields === []');
        $this->assertSame([], $findings->errors);
    }

    public function testAnInputWithoutRegisteredRulesFindsNothing(): void
    {
        $validator = new ObjectValidator(new ValidatorRegistry(), fieldPaths: true);

        $findings = (new ValidateInput($validator))(new OrderInput(null, [new OrderLine('A-1', 0)]));

        $this->assertSame([], $findings->fields);
    }

    public function testOnlyTheInjectedPortValidates(): void
    {
        $seen = [];
        $port = new class ($seen) implements ValidatorInterface {
            /** @param list<object> $seen */
            public function __construct(private array &$seen)
            {
            }

            public function validate(object $data): ValidationResult
            {
                $this->seen[] = $data;

                return new ValidationResult();
            }
        };
        $input = new OrderInput('Ada', []);

        (new ValidateInput($port))($input);

        $this->assertSame([$input], $seen, 'the port is asked exactly once, with the input as given');
        $types = array_map(
            static fn(\ReflectionParameter $p): string => (string) $p->getType(),
            (new \ReflectionMethod(ValidateInput::class, '__construct'))->getParameters()
        );
        $this->assertSame([ValidatorInterface::class, 'JardisSupport\Response\Handler\Failure\DescribeFieldFinding'], $types);
    }

    public function testEveryFieldCarriesAMessageKeyOfItsReason(): void
    {
        $findings = (new ValidateInput(OrderValidation::validator()))(
            new OrderInput(null, [new OrderLine('A-1', 2), new OrderLine('B-7', 0)])
        );

        $fields = array_column($findings->fields, null, 'field');
        $this->assertSame('validation.missing', $fields['customer']['messageKey']);
        $this->assertSame('validation.invalid', $fields['items[1].qty']['messageKey']);
        $this->assertNotSame('', $fields['customer']['message'], 'a validator text stays what it is');
    }

    public function testAnEmptyValidatorTextBecomesTheStandardTextAndStaysOutOfErrors(): void
    {
        $silent = new class implements ValidatorInterface {
            public function validate(object $data): ValidationResult
            {
                return new ValidationResult(
                    ['customer' => [''], 'note' => ['']],
                    ['customer' => ['missing'], 'note' => ['invalid']]
                );
            }
        };

        $findings = (new ValidateInput($silent))(new OrderInput(null, []));

        $this->assertSame(
            [
                ['field' => 'customer', 'reason' => 'missing', 'message' => 'Field is required.', 'messageKey' => 'validation.missing'],
                ['field' => 'note', 'reason' => 'invalid', 'message' => 'Field is invalid.', 'messageKey' => 'validation.invalid'],
            ],
            $findings->fields
        );
        $this->assertSame([], $findings->errors, 'errors keep the texts the validator delivered');
        $form = new ValidationFailure(ValidateInput::MESSAGE_KEY, $findings->fields);
        $data = (new BuildResponseData())(ResponseStatus::ValidationError, $form, null, 'PlaceOrder');
        $this->assertSame('validation.missing', $data['fields'][0]['messageKey']);
    }
}
