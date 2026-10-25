<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The package holds exactly the documented 58 classes (MODELL section 5, plus the answer closures of E2-P7, `ProjectAnswer`, `ProjectRows` and the request readers): no more, no fewer, each
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
        'Handler\\Answer\\AnswerInvalidRequest',
        'Handler\\Answer\\AnswerNotFound',
        'Handler\\Answer\\AnswerOutcome',
        'Handler\\Answer\\AnswerRead',
        'Handler\\Answer\\AnswerRuleViolation',
        'Handler\\Answer\\AnswerThrowable',
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
        'Handler\\Request\\Accept\\BodyBool',
        'Handler\\Request\\Accept\\BodyDate',
        'Handler\\Request\\Accept\\BodyDateTime',
        'Handler\\Request\\Accept\\BodyFloat',
        'Handler\\Request\\Accept\\BodyInt',
        'Handler\\Request\\Accept\\BodyStr',
        'Handler\\Request\\Accept\\BodyTime',
        'Handler\\Request\\Accept\\TransportBool',
        'Handler\\Request\\Accept\\TransportDate',
        'Handler\\Request\\Accept\\TransportDateTime',
        'Handler\\Request\\Accept\\TransportDecimal',
        'Handler\\Request\\Accept\\TransportFloat',
        'Handler\\Request\\Accept\\TransportInt',
        'Handler\\Request\\Accept\\TransportStr',
        'Handler\\Request\\Accept\\TransportTime',
        'Handler\\Request\\BuildRequestFindings',
        'Handler\\Request\\FindUnknownKeys',
        'Handler\\Request\\ReadBodyNullable',
        'Handler\\Request\\ReadRequired',
        'Handler\\Request\\ReadTransportList',
        'Handler\\Request\\ReadTransportOptional',
        'Handler\\Request\\RebaseFindings',
        'Handler\\Response\\BuildResponseData',
        'Handler\\Response\\ProjectAnswer',
        'Handler\\Response\\ProjectRows',
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

        $this->assertCount(58, self::CLASSES);
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
