<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the refresh token entity repository that can handle storefront users.
 */
class FrontendRefreshTokenRepository extends RefreshTokenRepository
{
    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker,
        private UserLoaderInterface $frontendUserLoader
    ) {
        parent::__construct($doctrine, $userLoader, $userChecker);
    }

    #[\Override]
    protected function getUserLoader(RefreshToken $token): UserLoaderInterface
    {
        if ($token->getAccessToken()->getClient()->isFrontend()) {
            return $this->frontendUserLoader;
        }

        return parent::getUserLoader($token);
    }

    #[\Override]
    protected function checkUser(UserLoaderInterface $userLoader, string $userIdentifier): void
    {
        if (!VisitorIdentifierUtil::isVisitorIdentifier($userIdentifier)) {
            parent::checkUser($userLoader, $userIdentifier);
        }
    }
}
