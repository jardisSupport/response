<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The package holds exactly the documented 36 classes (MODELL section 5, plus the answer closures of E2-P7 and `ProjectAnswer`): no more, no fewer, each
 * one loadable under `JardisSupport\Response\...`.
 */
class ResponseStructureTest extends TestCase
{
    private const CLASSES = [
        'Data\\Failure\\Conflict',
        'Data\\Failure\\FailureForm',
        'Data\\Failure\\NotFound',
        'Data\\Failure\\RuleViolation',
        'Data\\Failure\\ValidationFailure',
        'Data\\InputFindings',
        'Data\\MappedFailure',
        'Data\\ReadResult',
        'Data\\Reference',
        'Data\\Response\\ContextResponse',
        'Data\\Response\\DomainResponse',
        'Data\\RuleResult',
        'Exception\\ConfigurationException',
        'Handler\\Answer\\AnswerCommand',
        'Handler\\Answer\\AnswerFindings',
        'Handler\\Answer\\AnswerNotFound',
        'Handler\\Answer\\AnswerOutcome',
        'Handler\\Answer\\AnswerRead',
        'Handler\\Answer\\AnswerRuleViolation',
        'Handler\\Answer\\AnswerThrowable',
        'Handler\\Answer\\AnswerUnauthorized',
        'Handler\\Door\\CheckActor',
        'Handler\\Door\\ValidateInput',
        'Handler\\Failure\\BuildFailureForm',
        'Handler\\Failure\\BuildFailureOutcome',
        'Handler\\Failure\\ConcurrencyConflictPayload',
        'Handler\\Failure\\DescribeFieldFinding',
        'Handler\\Failure\\LogCause',
        'Handler\\Failure\\LogFailure',
        'Handler\\Failure\\MapThrowable',
        'Handler\\Failure\\NotFoundPayload',
        'Handler\\Failure\\RuleViolationPayload',
        'Handler\\Failure\\ValidationPayload',
        'Handler\\Response\\BuildResponseData',
        'Handler\\Response\\ProjectAnswer',
        'Handler\\Response\\TransformResponse',
    ];

    public function testPackageHoldsExactlyTheDocumentedClasses(): void
    {
        $root = dirname(__DIR__, 2) . '/src/';
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $found[] = str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
            }
        }
        sort($found);

        $this->assertCount(36, self::CLASSES);
        $this->assertSame(self::CLASSES, $found);
        foreach (self::CLASSES as $short) {
            $class = new ReflectionClass('JardisSupport\\Response\\' . $short);
            $this->assertSame('JardisSupport\\Response\\' . $short, $class->getName());
            $this->assertStringStartsWith('JardisSupport\\Response\\', $class->getNamespaceName() . '\\');
        }
    }

    public function testThereAreNoDoorStepsInTheOldStartDirectory(): void
    {
        $this->assertDirectoryDoesNotExist(dirname(__DIR__, 2) . '/src/Handler/Start');
        $this->assertDirectoryExists(dirname(__DIR__, 2) . '/src/Handler/Door');
    }
}
