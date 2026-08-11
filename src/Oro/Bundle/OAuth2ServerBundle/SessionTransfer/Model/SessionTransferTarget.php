<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model;

/**
 * Represents a validated route target and its context for a Session Transfer Token.
 */
final readonly class SessionTransferTarget
{
    /**
     * @param array<string, mixed> $routeParameters
     * @param array<string, mixed> $contextData
     */
    public function __construct(
        private string $route,
        private array $routeParameters,
        private array $contextData = []
    ) {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContextData(): array
    {
        return $this->contextData;
    }
}
