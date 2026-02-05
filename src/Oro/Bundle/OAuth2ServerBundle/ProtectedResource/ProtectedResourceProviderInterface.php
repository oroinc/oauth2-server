<?php

namespace Oro\Bundle\OAuth2ServerBundle\ProtectedResource;

/**
 * Represents a service that provides information about OAuth protected resources.
 */
interface ProtectedResourceProviderInterface
{
    /**
     * Finds an OAuth protected resource by its path.
     */
    public function getProtectedResource(string $resourcePath): ?ProtectedResource;
}
