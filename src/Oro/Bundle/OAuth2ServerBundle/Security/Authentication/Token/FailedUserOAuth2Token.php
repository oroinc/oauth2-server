<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * The authentication token that is used for exception handling when getting of OAuth2 access token.
 * @internal do not use this token as token for the authentication.
 */
class FailedUserOAuth2Token extends AbstractToken
{
    public function __construct(string $user = null)
    {
        $this->setUser($user);
        $this->setAuthenticated(false);
    }

    /**
     * {@inheritDoc}
     */
    public function getCredentials()
    {
        return $this->getAttribute('password');
    }
}
