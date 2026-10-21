<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Answer;

use JardisSupport\Factory\Factory;
use JardisSupport\Response\Handler\Answer\AnswerCommand;
use JardisSupport\Response\Handler\Answer\AnswerFindings;
use JardisSupport\Response\Handler\Answer\AnswerNotFound;
use JardisSupport\Response\Handler\Answer\AnswerOutcome;
use JardisSupport\Response\Handler\Answer\AnswerRead;
use JardisSupport\Response\Handler\Answer\AnswerRuleViolation;
use JardisSupport\Response\Handler\Answer\AnswerThrowable;
use JardisSupport\Response\Handler\Answer\AnswerUnauthorized;
use JardisSupport\Response\Handler\Failure\LogFailure;
use PHPUnit\Framework\TestCase;

/**
 * A generated door obtains a closure with `$this->handle(X::class)`, which resolves through the `Factory`:
 * a class with a required constructor parameter fails there (`Factory::createInstance`), so every answer step
 * must be creatable without arguments.
 */
class FactoryCreatableTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function closures(): array
    {
        $classes = [
            AnswerFindings::class,
            AnswerUnauthorized::class,
            AnswerNotFound::class,
            AnswerRuleViolation::class,
            AnswerOutcome::class,
            AnswerThrowable::class,
            AnswerCommand::class,
            AnswerRead::class,
            LogFailure::class,
        ];

        return array_combine($classes, array_map(static fn(string $class): array => [$class], $classes));
    }

    /**
     * @param class-string $class
     * @dataProvider closures
     */
    public function testTheFactoryCreatesTheClosureWithoutArguments(string $class): void
    {
        $this->assertInstanceOf($class, (new Factory())->get($class));
        $this->assertInstanceOf($class, (new Factory())->create($class));
    }

    /**
     * @param class-string $class
     * @dataProvider closures
     */
    public function testNoConstructorParameterIsRequired(string $class): void
    {
        $this->assertSame(0, (new \ReflectionClass($class))->getConstructor()?->getNumberOfRequiredParameters());
    }

    public function testTheLoggerOfTheLogStepIsPassedByName(): void
    {
        $logger = new \JardisSupport\Response\Tests\Support\Log\RecordingLogger();
        $step = (new Factory())->create(LogFailure::class, logger: $logger);

        $this->assertInstanceOf(LogFailure::class, $step);
    }
}
