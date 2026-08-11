<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;

/**
 * Delegates Session Transfer target validation to the validator matching the OAuth application context.
 */
class SessionTransferRouteValidator
{
    /**
     * @param iterable<SessionTransferRouteContextValidatorInterface> $validators
     */
    public function __construct(
        private readonly iterable $validators
    ) {
    }

    public function validate(
        string $route,
        array $routeParameters,
        Client $client
    ): SessionTransferTarget {
        foreach ($this->validators as $validator) {
            if (!$validator->supports($client)) {
                continue;
            }

            return $validator->validate($route, $routeParameters, $client);
        }

        throw OAuthServerException::invalidGrant(
            $client->isFrontend()
                ? 'Storefront Session Transfer is not available.'
                : 'Back-office Session Transfer is not available.'
        );
    }
}
