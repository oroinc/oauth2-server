<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the authorization code entity repository that can handle storefront users.
 */
class FrontendAuthCodeRepository extends AuthCodeRepository
{
    /** @var UserLoaderInterface */
    private $frontendUserLoader;

    /** @var CustomerVisitorManager */
    private $customerVisitorManager;

    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker,
        ClientManager $clientManager,
        UserLoaderInterface $frontendUserLoader,
        CustomerVisitorManager $customerVisitorManager
    ) {
        parent::__construct($doctrine, $userLoader, $userChecker, $clientManager);
        $this->frontendUserLoader = $frontendUserLoader;
        $this->customerVisitorManager = $customerVisitorManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserLoader(AuthCode $authCode): UserLoaderInterface
    {
        if ($authCode->getClient()->isFrontend()) {
            return $this->frontendUserLoader;
        }

        return parent::getUserLoader($authCode);
    }

    /**
     * {@inheritdoc}
     */
    protected function checkUser(UserLoaderInterface $userLoader, string $userIdentifier): void
    {
        if (VisitorIdentifierUtil::isVisitorIdentifier($userIdentifier)) {
            [$visitorId, $visitorSessionId] = VisitorIdentifierUtil::decodeIdentifier($userIdentifier);
            $visitor = $this->customerVisitorManager->find($visitorId, $visitorSessionId);
            if (null === $visitor) {
                throw OAuthServerException::invalidCredentials();
            }
        } else {
            parent::checkUser($userLoader, $userIdentifier);
        }
    }
}
