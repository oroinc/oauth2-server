<?php

namespace Oro\Bundle\OAuth2ServerBundle\ProtectedResource;

/**
 * Represents an OAuth protected resource.
 */
class ProtectedResource
{
    public function __construct(
        private readonly string $name,
        private readonly string $routeName,
        private readonly array $routeParameters,
        private readonly array $supportedScopes,
        private readonly array $options
    ) {
    }

    /**
     * Gets the resource name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the resource route name.
     */
    public function getRouteName(): string
    {
        return $this->routeName;
    }

    /**
     * Gets the resource route parameters.
     *
     * @return array [name => value, ...]
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    /**
     * Gets scopes supported by the resource.
     *
     * @return string[]
     */
    public function getSupportedScopes(): array
    {
        return $this->supportedScopes;
    }

    /**
     * Gets additional options for the resource.
     *
     * @return array [name => value, ...]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
