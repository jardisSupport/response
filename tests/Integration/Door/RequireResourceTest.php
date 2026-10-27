<?php

declare(strict_types=1);

namespace JardisSupport\Response\Tests\Integration\Door;

use JardisSupport\Response\Exception\ConfigurationException;
use JardisSupport\Response\Handler\Door\RequireResource;
use PHPUnit\Framework\TestCase;

/**
 * A resource the use case needs must be provided by the kernel: the very same object comes back,
 * a missing one is a wiring error.
 */
class RequireResourceTest extends TestCase
{
    public function testMissingResourceRaisesAConfigurationExceptionNamingIt(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Required resource "mailer" is not provided by the kernel');

        (new RequireResource())(null, 'mailer');
    }

    public function testMessageIsExactlyTheDocumentedText(): void
    {
        try {
            (new RequireResource())(null, 'clock');
            $this->fail('expected a ConfigurationException');
        } catch (ConfigurationException $e) {
            $this->assertSame('Required resource "clock" is not provided by the kernel', $e->getMessage());
        }
    }

    public function testProvidedResourceComesBackAsTheSameObject(): void
    {
        $resource = new \stdClass();

        $this->assertSame($resource, (new RequireResource())($resource, 'any'));
    }
}
