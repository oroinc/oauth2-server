<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\FailedUserOAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\UserBundle\Security\LoginSourceProviderForFailedRequestInterface;
use Oro\Bundle\UserBundle\Security\LoginSourceProviderForSuccessRequestInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Detects the OAuth login source.
 */
class OauthLoginSourceProvider implements
    LoginSourceProviderForSuccessRequestInterface,
    LoginSourceProviderForFailedRequestInterface
{
    /**
     * {@inheritDoc}
     */
    public function getLoginSourceForFailedRequest(TokenInterface $token, \Exception $exception): ?string
    {
        if (is_a($token, FailedUserOAuth2Token::class) || is_a($token, OAuth2Token::class)) {
            return 'OAuth';
        }

        // if exception has been thrown at the OAuth login page, log it as from the OAuthCode source.
        if (is_a($token, UsernamePasswordToken::class)
            && \in_array(
                $token->getFirewallName(),
                ['oauth2_authorization_authenticate', 'oauth2_frontend_authorization_authenticate']
            )) {
            return 'OAuthCode';
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getLoginSourceForSuccessRequest(TokenInterface $token): ?string
    {
        if (is_a($token, OAuth2Token::class)) {
            return 'OAuth';
        }

        return null;
    }
}
