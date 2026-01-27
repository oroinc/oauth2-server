<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Grant;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant as LeaguePasswordGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The password grant.
 * Adds check if user_login_password feature enabled. And if it disabled, disallow to use this grant type.
 */
class PasswordGrant extends LeaguePasswordGrant
{
    private FeatureChecker $featureChecker;

    public function __construct(
        UserRepositoryInterface $userRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        FeatureChecker $featureChecker
    ) {
        parent::__construct($userRepository, $refreshTokenRepository);
        $this->featureChecker = $featureChecker;
    }

    #[\Override]
    protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client)
    {
        if (
            (!$client->isFrontend() && !$this->featureChecker->isFeatureEnabled('user_login_password'))
            || ($client->isFrontend() && !$this->featureChecker->isFeatureEnabled('customer_user_login_password'))
        ) {
            throw OAuthServerException::invalidCredentials();
        }

        return parent::validateUser($request, $client);
    }
}
