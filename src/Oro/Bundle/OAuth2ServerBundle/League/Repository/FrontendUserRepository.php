<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * The implementation of the user entity repository that can handle storefront users.
 */
class FrontendUserRepository extends UserRepository
{
    /** @var UserLoaderInterface */
    private $frontendUserLoader;

    /**
     * @param UserLoaderInterface     $userLoader
     * @param EncoderFactoryInterface $encoderFactory
     * @param OAuthUserChecker        $userChecker
     * @param UserLoaderInterface     $frontendUserLoader
     */
    public function __construct(
        UserLoaderInterface $userLoader,
        EncoderFactoryInterface $encoderFactory,
        OAuthUserChecker $userChecker,
        UserLoaderInterface $frontendUserLoader
    ) {
        parent::__construct($userLoader, $encoderFactory, $userChecker);
        $this->frontendUserLoader = $frontendUserLoader;
    }

    /**
     * @param ClientEntityInterface $clientEntity
     *
     * @return UserLoaderInterface
     */
    protected function getUserLoader(ClientEntityInterface $clientEntity): UserLoaderInterface
    {
        if ($clientEntity instanceof ClientEntity && $clientEntity->isFrontend()) {
            return $this->frontendUserLoader;
        }

        return parent::getUserLoader($clientEntity);
    }
}
