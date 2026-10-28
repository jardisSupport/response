<?php

declare(strict_types=1);

namespace JardisSupport\Response\Handler\Door;

use JardisSupport\Response\Exception\ConfigurationException;

/**
 * Door step: a resource the use case needs must be provided by the kernel.
 *
 * Returns the very same object when it is there. A missing resource is a wiring error of the
 * runtime, not of the request: it raises a {@see ConfigurationException} naming the resource, the
 * door answers it through `MapThrowable` as 500.
 *
 * Atomic closure - one entry point.
 */
final class RequireResource
{
    /**
     * @throws ConfigurationException when the kernel provides no such resource
     */
    public function __invoke(?object $resource, string $name): object
    {
        if ($resource === null) {
            throw new ConfigurationException(sprintf('Required resource "%s" is not provided by the kernel', $name));
        }

        return $resource;
    }
}
