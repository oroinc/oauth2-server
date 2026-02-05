<?php

namespace Oro\Bundle\OAuth2ServerBundle\ProtectedResource;

/**
 * Provides information about OAuth protected resources from a predefined list.
 */
class ProtectedResourceProvider implements ProtectedResourceProviderInterface
{
    public function __construct(
        private readonly array $resources
    ) {
    }

    #[\Override]
    public function getProtectedResource(string $resourcePath): ?ProtectedResource
    {
        $resource = $this->resources[$resourcePath] ?? null;
        if (null === $resource) {
            return null;
        }

        return new ProtectedResource(
            $resource['name'],
            $resource['route'],
            $resource['route_params'],
            $resource['supported_scopes'],
            $resource['options']
        );
    }
}
