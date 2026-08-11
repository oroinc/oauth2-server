<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Routing\Route;

/**
 * Validates target routes for back-office OAuth applications.
 */
final class BackendSessionTransferRouteContextValidator extends AbstractSessionTransferRouteContextValidator
{
    #[\Override]
    public function supports(Client $client): bool
    {
        return !$client->isFrontend();
    }

    #[\Override]
    protected function validateRouteContext(string $route, Route $routeDefinition): void
    {
        if (true !== $routeDefinition->getOption('frontend')) {
            return;
        }

        throw OAuthServerException::invalidRequest(
            'route',
            \sprintf(
                'The route "%s" is a storefront route and cannot be used by a back-office OAuth application.',
                $route
            )
        );
    }

    #[\Override]
    protected function getContextData(Client $client): array
    {
        return [];
    }
}
