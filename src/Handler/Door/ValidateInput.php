<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Door;

use JardisSupport\Contract\Validation\ValidatorInterface;
use JardisSupport\Response\Data\InputFindings;
use JardisSupport\Response\Handler\Failure\DescribeFieldFinding;

/**
 * Door step: checks the input with the injected validator port.
 *
 * The findings name every failing field (`field`, `reason`, `message`, `messageKey`, see
 * {@see DescribeFieldFinding}); `errors` carries the texts the validator delivered. The input is
 * valid exactly when `fields === []` - the door then answers 400 before any work starts.
 */
final class ValidateInput
{
    public const string MESSAGE_KEY = 'process.inputInvalid';

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly DescribeFieldFinding $describeFieldFinding = new DescribeFieldFinding(),
    ) {
    }

    public function __invoke(object $input): InputFindings
    {
        $result = $this->validator->validate($input);
        if ($result->isValid()) {
            return new InputFindings([], []);
        }

        $findings = $result->toFields(lcfirst((new \ReflectionClass($input))->getShortName()));
        $messages = array_filter(
            array_column($findings, 'message'),
            static fn(string $message): bool => $message !== ''
        );

        return new InputFindings(
            array_map($this->describeFieldFinding, $findings),
            array_values($messages)
        );
    }
}
