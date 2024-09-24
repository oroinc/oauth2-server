<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\SecurityBundle\Authentication\Authenticator\UsernamePasswordOrganizationAuthenticator;
use Oro\Bundle\UserBundle\Security\LoginSourceProviderForFailedRequestInterface;
use Oro\Bundle\UserBundle\Security\LoginSourceProviderForSuccessRequestInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * Detects the OAuth login source.
 */
class OauthLoginSourceProvider implements
    LoginSourceProviderForSuccessRequestInterface,
    LoginSourceProviderForFailedRequestInterface
{
    #[\Override]
    public function getLoginSourceForFailedRequest(
        AuthenticatorInterface $authenticator,
        \Exception $exception
    ): ?string {
        if (is_a($authenticator, OAuth2Authenticator::class)) {
            return 'OAuth';
        }

        if (is_a($authenticator, UsernamePasswordOrganizationAuthenticator::class)
            && \in_array(
                $authenticator->getFirewallName(),
                ['oauth2_authorization_authenticate', 'oauth2_frontend_authorization_authenticate']
            )) {
            return 'OAuthCode';
        }

        return null;
    }

    #[\Override]
    public function getLoginSourceForSuccessRequest(TokenInterface $token): ?string
    {
        if (is_a($token, OAuth2Token::class)) {
            return 'OAuth';
        }

        return null;
    }
}
