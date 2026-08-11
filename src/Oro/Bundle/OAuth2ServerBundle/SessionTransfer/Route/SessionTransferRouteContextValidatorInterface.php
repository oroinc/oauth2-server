<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;

/**
 * Validates Session Transfer target routes for a specific OAuth application context.
 */
interface SessionTransferRouteContextValidatorInterface
{
    public function supports(Client $client): bool;

    /**
     * @param array<string, mixed> $routeParameters
     *
     * @throws OAuthServerException
     */
    public function validate(string $route, array $routeParameters, Client $client): SessionTransferTarget;
}
