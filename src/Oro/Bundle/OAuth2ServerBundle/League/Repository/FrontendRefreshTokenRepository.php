<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * The implementation of the refresh token entity repository that can handle storefront users.
 */
class FrontendRefreshTokenRepository extends RefreshTokenRepository
{
    /** @var UserLoaderInterface */
    private $frontendUserLoader;

    /**
     * @param ManagerRegistry     $doctrine
     * @param UserLoaderInterface $userLoader
     * @param OAuthUserChecker    $userChecker
     * @param UserLoaderInterface $frontendUserLoader
     */
    public function __construct(
        ManagerRegistry $doctrine,
        UserLoaderInterface $userLoader,
        OAuthUserChecker $userChecker,
        UserLoaderInterface $frontendUserLoader
    ) {
        parent::__construct($doctrine, $userLoader, $userChecker);
        $this->frontendUserLoader = $frontendUserLoader;
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
}
