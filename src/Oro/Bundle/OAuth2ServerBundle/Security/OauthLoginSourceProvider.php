<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\SecurityBundle\Authentication\Authenticator\UsernamePasswordOrganizationAuthenticator;
use Oro\Bundle\UserBundle\Security\LoginSourceProviderForFailedRequestInterface;
use Oro\Bundle\UserBundle\Security\LoginSourceProviderForSuccessRequestInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * Detects the OAuth login source.
 */
class OauthLoginSourceProvider implements
    LoginSourceProviderForSuccessRequestInterface,
    LoginSourceProviderForFailedRequestInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    #[\Override]
    public function getLoginSourceForFailedRequest(
        AuthenticatorInterface $authenticator,
        \Exception $exception
    ): ?string {
        if (is_a($authenticator, OAuth2Authenticator::class)) {
            return 'OAuth';
        }

        $request = $this->requestStack->getCurrentRequest();
        if (
            $request !== null
            && is_a($authenticator, UsernamePasswordOrganizationAuthenticator::class)
            && $request->attributes->get('_oauth_login', false)
        ) {
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
