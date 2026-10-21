<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * The package knows no process engine (M5): no public signature names an engine type, and the
 * public signatures are the documented ones (PAKET-APIS section 2), checked by reflection.
 */
class DecouplingTest extends TestCase
{
    /**
     * The documented public surface per class: constants, public properties and public methods
     * declared by the class itself, type names without namespace; `= …` marks an optional parameter.
     */
    private const SIGNATURES = [
        'Data\\Failure\\Conflict' => [
            '__construct(string $messageKey, array $context = …, ?string $reason = …, ?string $aggregate = …, array $violated = …)',
            'status(): ResponseStatus',
            'messageKey(): string',
            'context(): array',
            'reason(): ?string',
            'aggregate(): ?string',
            'violated(): array',
        ],
        'Data\\Failure\\FailureForm' => [
            'status(): ResponseStatus',
            'messageKey(): string',
            'context(): array',
        ],
        'Data\\Failure\\NotFound' => [
            '__construct(string $messageKey, array $context = …)',
            'status(): ResponseStatus',
            'messageKey(): string',
            'context(): array',
        ],
        'Data\\Failure\\RuleViolation' => [
            '__construct(string $rule, string $messageKey, array $context = …)',
            'status(): ResponseStatus',
            'messageKey(): string',
            'context(): array',
            'rule(): string',
        ],
        'Data\\Failure\\ValidationFailure' => [
            '__construct(string $messageKey, array $fields, array $context = …)',
            'status(): ResponseStatus',
            'messageKey(): string',
            'context(): array',
            'fields(): array',
        ],
        'Data\\InputFindings' => [
            '__construct(array $fields, array $errors)',
            'failed(): bool',
            'prop array $fields',
            'prop array $errors',
        ],
        'Data\\MappedFailure' => [
            '__construct(ResponseStatus $status, FailureForm|stdClass $response)',
            'prop ResponseStatus $status',
            'prop FailureForm|stdClass $response',
        ],
        'Data\\ReadResult' => [
            'static ofList(array $items, int $total): self',
            'static ofOne(mixed $item): self',
            'items(): array',
            'total(): int',
            'first(): mixed',
            'isEmpty(): bool',
        ],
        'Data\\Reference' => [
            'const string DEFAULT_IDENTIFIER',
            '__construct(string|int $root, array $children = …, string $identifier = …)',
            'root(): string|int',
            'children(): array',
            'identifier(): string',
        ],
        'Data\\RuleResult' => [
            'static pass(): self',
            'static reject(string $rule, string $messageKey, array $context = …): self',
            'prop bool $passed',
            'prop string $rule',
            'prop string $messageKey',
            'prop array $context',
        ],
        'Data\\Response\\ContextResponse' => [
            '__construct(string $context)',
            'addData(string $key, mixed $value): self',
            'setData(array $data): self',
            'getData(): array',
            'addEvent(object $event, EventScope $scope = …): self',
            'getEvents(?EventScope $scope = …): array',
            'addError(string $message): self',
            'getErrors(): array',
            'addResult(ContextResponseInterface $result): self',
            'getResults(): array',
        ],
        'Data\\Response\\DomainResponse' => [
            '__construct(ResponseStatus $status, array $data, array $events, array $errors, array $metadata, array $eventsByScope = …)',
            'isSuccess(): bool',
            'getStatus(): int',
            'getData(): array',
            'getEvents(?EventScope $scope = …): array',
            'getErrors(): array',
            'getMetadata(): array',
        ],
        'Handler\\Answer\\AnswerCommand' => [
            '__construct(BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, Reference $reference, string $aggregate): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerFindings' => [
            'const string MESSAGE_KEY',
            '__construct(ValidationPayload $validationPayload = …, BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, InputFindings $findings): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerInvalidRequest' => [
            'const string CONTEXT',
            '__construct(ClockInterface $clock, BuildRequestFindings $buildRequestFindings = …, AnswerFindings $answerFindings = …)',
            '__invoke(array $fields, string $version = …): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerNotFound' => [
            'const string MESSAGE_KEY',
            '__construct(NotFoundPayload $notFoundPayload = …, BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, array $context): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerOutcome' => [
            '__construct(BuildFailureOutcome $buildFailureOutcome = …, BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, AnswerableOutcomeInterface $outcome, string $processName): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerRead' => [
            '__construct(BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, ReadResult $found, string $type): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerRuleViolation' => [
            '__construct(RuleViolationPayload $ruleViolationPayload = …, BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, RuleResult $rejection): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerThrowable' => [
            '__construct(MapThrowable $mapThrowable = …, BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform, Throwable $failure, bool $owner, string $aggregate): DomainResponseInterface',
        ],
        'Handler\\Answer\\AnswerUnauthorized' => [
            '__construct(BuildFailureOutcome $buildFailureOutcome = …, BuildResponseData $buildResponseData = …)',
            '__invoke(ContextResponseInterface $result, TransformResponse $transform): DomainResponseInterface',
        ],
        'Handler\\Door\\CheckActor' => [
            'const string MESSAGE_KEY',
            '__invoke(bool $required, ?object $actor): bool',
        ],
        'Handler\\Door\\ValidateInput' => [
            'const string MESSAGE_KEY',
            '__construct(ValidatorInterface $validator, DescribeFieldFinding $describeFieldFinding = …)',
            '__invoke(object $input): InputFindings',
        ],
        'Handler\\Failure\\BuildFailureForm' => [
            '__construct(ValidationPayload $validation = …, NotFoundPayload $notFound = …, ConcurrencyConflictPayload $conflict = …, RuleViolationPayload $ruleViolation = …)',
            '__invoke(ResponseStatus $status, string $messageKey, array $context, array $errors): ?FailureForm',
        ],
        'Handler\\Failure\\BuildFailureOutcome' => [
            '__construct(BuildFailureForm $buildFailureForm = …)',
            '__invoke(ResponseStatus $status, string $messageKey, array $context = …, array $errors = …, bool $declared = …): FailureForm|stdClass',
        ],
        'Handler\\Failure\\ConcurrencyConflictPayload' => [
            '__invoke(string $messageKey, string $reason, string $aggregate, array $context, array $violated): Conflict',
        ],
        'Handler\\Failure\\DescribeFieldFinding' => [
            'const string KEY_PREFIX',
            'const string REASON_MISSING',
            'const string REASON_INVALID',
            'const string REASON_TYPE',
            'const string REASON_UNKNOWN',
            'const string TEXT_MISSING',
            'const string TEXT_INVALID',
            'const string TEXT_TYPE',
            'const string TEXT_UNKNOWN',
            '__invoke(array $finding): array',
        ],
        'Handler\\Failure\\LogCause' => [
            '__construct(?LoggerInterface $logger)',
            '__invoke(Throwable $cause, string $process): void',
        ],
        'Handler\\Failure\\LogFailure' => [
            '__construct(?LoggerInterface $logger = …, ?LogCause $logCause = …)',
            '__invoke(Throwable $cause, string $process, bool $owner, DomainResponseInterface $answer): void',
        ],
        'Handler\\Failure\\MapThrowable' => [
            'const string CONFLICT_KEY',
            'const string CONFLICT_REASON',
            '__construct(BuildFailureOutcome $buildFailureOutcome = …)',
            '__invoke(Throwable $failure, bool $owner, string $aggregate): MappedFailure',
        ],
        'Handler\\Failure\\NotFoundPayload' => [
            '__invoke(string $messageKey, array $context): NotFound',
        ],
        'Handler\\Failure\\RuleViolationPayload' => [
            '__invoke(string $rule, string $messageKey, array $context): RuleViolation',
        ],
        'Handler\\Failure\\ValidationPayload' => [
            '__invoke(string $messageKey, array $fields, array $context = …): ValidationFailure',
        ],
        'Handler\\Request\\Accept\\BodyBool' => [
            '__invoke(mixed $value): ?bool',
        ],
        'Handler\\Request\\Accept\\BodyFloat' => [
            '__invoke(mixed $value): ?float',
        ],
        'Handler\\Request\\Accept\\BodyInt' => [
            '__invoke(mixed $value): ?int',
        ],
        'Handler\\Request\\Accept\\BodyStr' => [
            '__invoke(mixed $value): ?string',
        ],
        'Handler\\Request\\Accept\\TransportBool' => [
            '__invoke(mixed $value): ?bool',
        ],
        'Handler\\Request\\Accept\\TransportFloat' => [
            '__invoke(mixed $value): ?float',
        ],
        'Handler\\Request\\Accept\\TransportInt' => [
            '__invoke(mixed $value): ?int',
        ],
        'Handler\\Request\\Accept\\TransportStr' => [
            '__invoke(mixed $value): ?string',
        ],
        'Handler\\Request\\Accept\\BodyDate' => [
            '__construct(ValueValidatorInterface $validator)',
            '__invoke(mixed $value): ?DateTimeImmutable',
        ],
        'Handler\\Request\\Accept\\BodyDateTime' => [
            '__construct(ValueValidatorInterface $validator)',
            '__invoke(mixed $value): ?DateTimeImmutable',
        ],
        'Handler\\Request\\Accept\\BodyTime' => [
            '__construct(ValueValidatorInterface $validator)',
            '__invoke(mixed $value): ?DateTimeImmutable',
        ],
        'Handler\\Request\\Accept\\TransportDate' => [
            '__construct(ValueValidatorInterface $validator)',
            '__invoke(mixed $value): ?string',
        ],
        'Handler\\Request\\Accept\\TransportDateTime' => [
            '__construct(ValueValidatorInterface $validator)',
            '__invoke(mixed $value): ?string',
        ],
        'Handler\\Request\\Accept\\TransportTime' => [
            '__construct(ValueValidatorInterface $validator)',
            '__invoke(mixed $value): ?string',
        ],
        'Handler\\Request\\Accept\\TransportDecimal' => [
            'const string LEXICAL_PATTERN',
            '__construct(string $pattern = …)',
            '__invoke(mixed $value): ?string',
        ],
        'Handler\\Request\\BuildRequestFindings' => [
            'const string REASON_MISSING',
            'const string REASON_TYPE',
            'const string REASON_UNKNOWN',
            '__construct(DescribeFieldFinding $describe = …)',
            '__invoke(array $fields): InputFindings',
        ],
        'Handler\\Request\\FindUnknownKeys' => [
            '__invoke(array $source, array $known, array $fields): void',
        ],
        'Handler\\Request\\ReadBodyNullable' => [
            '__invoke(array $body, string $name, callable $accept, array $fields): mixed',
        ],
        'Handler\\Request\\ReadRequired' => [
            '__invoke(array $source, string $name, callable $accept, mixed $fallback, array $fields): mixed',
        ],
        'Handler\\Request\\ReadTransportList' => [
            '__invoke(array $source, string $name, callable $accept, array $fields): array',
        ],
        'Handler\\Request\\ReadTransportOptional' => [
            '__invoke(array $source, string $name, callable $accept, array $fields): mixed',
        ],
        'Handler\\Request\\RebaseFindings' => [
            '__invoke(string $path, array $found, array $fields): void',
        ],
        'Handler\\Response\\BuildResponseData' => [
            '__invoke(ResponseStatus $status, object $response, ?string $declaredMessageKey, string $processName): array',
        ],
        'Handler\\Response\\ProjectAnswer' => [
            '__invoke(DomainResponseInterface $response, string ...$fields): DomainResponseInterface',
        ],
        'Handler\\Response\\TransformResponse' => [
            '__construct(ClockInterface $clock, string $version = …)',
            '__invoke(ContextResponseInterface $result, ?ResponseStatus $status = …): DomainResponseInterface',
        ],
    ];

    /**
     * The namespace of the process engine, assembled so that the M5 grep over `tests/` stays at zero.
     */
    private static function engine(): string
    {
        return implode('\\', ['JardisSupport', 'Workflow']);
    }

    /**
     * @return list<class-string> every class and interface below src/
     */
    private function classes(): array
    {
        $root = dirname(__DIR__, 2) . '/src/';
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                /** @var class-string $class */
                $class = 'JardisSupport\\Response\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }

    /**
     * @return list<string> the fully qualified names a type mentions
     */
    private function names(?ReflectionType $type): array
    {
        return match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_merge(...array_map(fn(ReflectionType $t): array => $this->names($t), $type->getTypes())),
            default => [],
        };
    }

    private function short(?ReflectionType $type): string
    {
        return (string) preg_replace('/(?:[A-Za-z0-9_]+\\\\)+/', '', (string) $type);
    }

    /**
     * @return list<string>
     */
    private function surface(ReflectionClass $class): array
    {
        $lines = [];
        foreach ($class->getReflectionConstants() as $constant) {
            if ($constant->isPublic() && $constant->getDeclaringClass()->getName() === $class->getName()) {
                $lines[] = 'const ' . $this->short($constant->getType()) . ' ' . $constant->getName();
            }
        }
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $parameters = array_map(
                fn(\ReflectionParameter $p): string => $this->short($p->getType()) . ($p->isVariadic() ? ' ...$' : ' $') . $p->getName() . ($p->isOptional() && !$p->isVariadic() ? ' = …' : ''),
                $method->getParameters()
            );
            $lines[] = ($method->isStatic() ? 'static ' : '') . $method->getName() . '(' . implode(', ', $parameters) . ')'
                . ($method->hasReturnType() ? ': ' . $this->short($method->getReturnType()) : '');
        }
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $lines[] = 'prop ' . $this->short($property->getType()) . ' $' . $property->getName();
        }

        return $lines;
    }

    public function testNoPublicSignatureNamesAnEngineType(): void
    {
        $classes = $this->classes();
        $this->assertCount(59, $classes);

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertNotSame(self::engine(), $reflection->getNamespaceName(), $class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $types = array_merge(
                    $this->names($method->getReturnType()),
                    ...array_map(fn(\ReflectionParameter $p): array => $this->names($p->getType()), $method->getParameters())
                );
                foreach ($types as $name) {
                    $this->assertStringStartsNotWith(self::engine() . '\\', $name, $class . '::' . $method->getName());
                }
            }
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                foreach ($this->names($property->getType()) as $name) {
                    $this->assertStringStartsNotWith(self::engine() . '\\', $name, $class . '::$' . $property->getName());
                }
            }
            foreach (array_merge($reflection->getInterfaceNames(), $reflection->getParentClass() ? [$reflection->getParentClass()->getName()] : []) as $name) {
                $this->assertStringStartsNotWith(self::engine() . '\\', $name, $class . ' extends/implements');
            }
        }
    }

    public function testSignaturesMatchTheDocumentedOnes(): void
    {
        foreach (self::SIGNATURES as $short => $expected) {
            $class = new ReflectionClass('JardisSupport\\Response\\' . $short);

            $this->assertSame($expected, $this->surface($class), $short);
        }
    }

    public function testTheDocumentedClassKindsAndConstantValuesHold(): void
    {
        $kind = static fn(string $short): ReflectionClass => new ReflectionClass('JardisSupport\\Response\\' . $short);

        $this->assertTrue($kind('Data\\Failure\\FailureForm')->isInterface());
        $this->assertTrue($kind('Data\\ReadResult')->isFinal() && $kind('Data\\ReadResult')->isReadOnly());
        $this->assertTrue($kind('Data\\InputFindings')->isFinal() && $kind('Data\\InputFindings')->isReadOnly());
        $this->assertTrue($kind('Data\\MappedFailure')->isFinal() && $kind('Data\\MappedFailure')->isReadOnly());
        $this->assertTrue($kind('Data\\Response\\DomainResponse')->isReadOnly());
        $this->assertTrue($kind('Exception\\ConfigurationException')->isFinal());
        $this->assertSame(\RuntimeException::class, $kind('Exception\\ConfigurationException')->getParentClass()->getName());
        $this->assertSame('process.inputInvalid', $kind('Handler\\Door\\ValidateInput')->getConstant('MESSAGE_KEY'));
        $this->assertSame('process.actorMissing', $kind('Handler\\Door\\CheckActor')->getConstant('MESSAGE_KEY'));
        $this->assertSame('process.transientConflict', $kind('Handler\\Failure\\MapThrowable')->getConstant('CONFLICT_KEY'));
        $this->assertSame('stale_write', $kind('Handler\\Failure\\MapThrowable')->getConstant('CONFLICT_REASON'));
    }
}
