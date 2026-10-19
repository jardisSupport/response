<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The package holds exactly the documented 25 classes (MODELL section 5): no more, no fewer, each
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
        'Exception\\ConfigurationException',
        'Handler\\Door\\CheckActor',
        'Handler\\Door\\ValidateInput',
        'Handler\\Failure\\BuildFailureForm',
        'Handler\\Failure\\BuildFailureOutcome',
        'Handler\\Failure\\ConcurrencyConflictPayload',
        'Handler\\Failure\\DescribeFieldFinding',
        'Handler\\Failure\\LogCause',
        'Handler\\Failure\\MapThrowable',
        'Handler\\Failure\\NotFoundPayload',
        'Handler\\Failure\\RuleViolationPayload',
        'Handler\\Failure\\ValidationPayload',
        'Handler\\Response\\BuildResponseData',
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

        $this->assertCount(25, self::CLASSES);
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
