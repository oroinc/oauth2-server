<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the authorization code entity repository that can handle storefront users.
 */
class FrontendAuthCodeRepository extends AuthCodeRepository
{
    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker,
        ClientManager $clientManager,
        private UserLoaderInterface $frontendUserLoader
    ) {
        parent::__construct($doctrine, $userLoader, $userChecker, $clientManager);
    }

    #[\Override]
    protected function getUserLoader(AuthCode $authCode): UserLoaderInterface
    {
        if ($authCode->getClient()->isFrontend()) {
            return $this->frontendUserLoader;
        }

        return parent::getUserLoader($authCode);
    }

    #[\Override]
    protected function checkUser(UserLoaderInterface $userLoader, string $userIdentifier): void
    {
        if (VisitorIdentifierUtil::isVisitorIdentifier($userIdentifier)) {
            return;
        }

        [$userIdentifier] = AuthCodeGrantUserIdentifierUtil::decodeIdentifier($userIdentifier);
        parent::checkUser($userLoader, $userIdentifier);
    }
}
