<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the refresh token entity repository that can handle storefront users.
 */
class FrontendRefreshTokenRepository extends RefreshTokenRepository
{
    /** @var UserLoaderInterface */
    private $frontendUserLoader;

    /** @var CustomerVisitorManager */
    private $customerVisitorManager;

    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker,
        UserLoaderInterface $frontendUserLoader,
        CustomerVisitorManager $customerVisitorManager
    ) {
        parent::__construct($doctrine, $userLoader, $userChecker);
        $this->frontendUserLoader = $frontendUserLoader;
        $this->customerVisitorManager = $customerVisitorManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserLoader(RefreshToken $token): UserLoaderInterface
    {
        if ($token->getAccessToken()->getClient()->isFrontend()) {
            return $this->frontendUserLoader;
        }

        return parent::getUserLoader($token);
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
                throw OAuthServerException::invalidGrant();
            }
        } else {
            parent::checkUser($userLoader, $userIdentifier);
        }
    }
}
