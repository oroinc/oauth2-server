<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authenticator;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Security\ClientOwnerScopeValidator;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * OAuth2 authenticator for storefront requests.
 */
class FrontendOAuth2Authenticator extends OAuth2Authenticator
{
    private ?ClientOwnerScopeValidator $clientOwnerScopeValidator = null;

    public function setClientOwnerScopeValidator(ClientOwnerScopeValidator $clientOwnerScopeValidator): void
    {
        $this->clientOwnerScopeValidator = $clientOwnerScopeValidator;
    }

    #[\Override]
    protected function getUser(Client $client, ServerRequestInterface $request): object
    {
        if ($client->isFrontend()) {
            if (null === $this->clientOwnerScopeValidator) {
                throw new \LogicException(
                    'The client owner scope validator must be configured for frontend OAuth applications.'
                );
            }
            if (!$this->clientOwnerScopeValidator->isValid($client, $request->getAttribute('oauth_user_id'))) {
                throw new AuthenticationException('The user is outside of the OAuth application owner scope.');
            }
        }

        return parent::getUser($client, $request);
    }
}
