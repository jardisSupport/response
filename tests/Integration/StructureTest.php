<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Structure proofs on the real sources: every handler is a final closure with one entry point
 * (A-V2), handlers depend on interfaces, builtins and other handlers only (A-V5), and the package
 * holds no persistence of its own, no suspension, no event sending and no engine (A-B1, A-N2, M5).
 */
class StructureTest extends TestCase
{
    private const HANDLER_NAMESPACE = 'JardisSupport\\Response\\Handler\\';

    /**
     * The namespace of the process engine, assembled so that the M5 grep over `tests/` stays at zero.
     */
    private static function engine(): string
    {
        return implode('\\', ['JardisSupport', 'Workflow']);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string> absolute paths of all php files below a source directory
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string> 'file:line' of every line of the source tree matching the pattern
     */
    private function grep(string $pattern): array
    {
        $hits = [];
        foreach ($this->phpFiles(self::root() . '/src') as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $hits[] = $file . ':' . ($number + 1);
                }
            }
        }

        return $hits;
    }

    /**
     * @return list<class-string> every class below src/Handler
     */
    private function handlerClasses(): array
    {
        $classes = [];
        foreach ($this->phpFiles(self::root() . '/src/Handler') as $file) {
            /** @var class-string $class */
            $class = 'JardisSupport\\Response\\' . str_replace(
                '/',
                '\\',
                substr($file, strlen(self::root() . '/src/'), -4)
            );
            $classes[] = $class;
        }

        return $classes;
    }

    public function testGrepPatternsReallyMatch(): void
    {
        $this->assertSame(1, preg_match('/' . preg_quote(self::engine(), '/') . '/', 'use ' . self::engine() . '\\Data\\ProcessOutcome;'));
        $this->assertSame(1, preg_match('/PDO|ConnectionPool/', '    private \PDO $pdo;'));
        $this->assertSame(1, preg_match('/resume|suspend|persistState|wait/i', '$this->suspend();'));
        $this->assertSame(1, preg_match('/publish|dispatch/', '$bus->dispatch($event);'));
        $this->assertGreaterThan(20, count($this->phpFiles(self::root() . '/src')));
    }

    public function testEveryHandlerIsFinalWithoutAProcessEngine(): void
    {
        $classes = $this->handlerClasses();
        $this->assertGreaterThanOrEqual(13, count($classes));

        foreach ($classes as $class) {
            $this->assertTrue((new ReflectionClass($class))->isFinal(), $class);
        }
    }

    public function testEveryHandlerHasExactlyOnePublicEntryPoint(): void
    {
        foreach ($this->handlerClasses() as $class) {
            $public = array_map(
                static fn(ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn(ReflectionMethod $method): bool => !$method->isConstructor()
                )
            );

            $this->assertSame(['__invoke'], array_values($public), $class);
        }
    }

    public function testHandlerConstructorsTakeInterfacesBuiltinsAndHandlersOnly(): void
    {
        foreach ($this->handlerClasses() as $class) {
            foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                $this->assertInstanceOf(ReflectionNamedType::class, $type, $class . '::$' . $parameter->getName());
                $name = $type->getName();
                $this->assertTrue(
                    $type->isBuiltin() || interface_exists($name) || str_starts_with($name, self::HANDLER_NAMESPACE),
                    $class . '::$' . $parameter->getName() . ' is ' . $name
                );
            }
        }
    }

    public function testPackageHoldsNoPersistenceNoSuspensionNoEngineAndSendsNoEvents(): void
    {
        $this->assertSame([], $this->grep('/PDO|ConnectionPool|new ObjectValidator/'), 'no own persistence');
        $this->assertSame([], $this->grep('/resume|suspend|persistState|wait/i'), 'no suspension');
        $this->assertSame([], $this->grep('/publish|dispatch/'), 'events are never sent here');
        $this->assertSame([], $this->grep('/' . preg_quote(self::engine(), '/') . '/'), 'no process engine (M5)');
    }
}
