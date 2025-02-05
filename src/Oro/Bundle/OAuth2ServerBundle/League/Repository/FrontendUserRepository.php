<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * The implementation of the user entity repository that can handle storefront users.
 */
class FrontendUserRepository extends UserRepository
{
    private const VISITOR_USERNAME = 'guest';
    private const VISITOR_PASSWORD = 'guest';

    public function __construct(
        UserLoaderInterface $userLoader,
        PasswordHasherFactoryInterface $passwordHasherFactory,
        OAuthUserChecker $userChecker,
        private UserLoaderInterface $frontendUserLoader,
        private CustomerVisitorManager $visitorManager
    ) {
        parent::__construct($userLoader, $passwordHasherFactory, $userChecker);
    }

    #[\Override]
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {
        if ($this->isFrontendClient($clientEntity)
            && self::VISITOR_USERNAME === $username
            && self::VISITOR_PASSWORD === $password
        ) {
            if ('password' === $grantType) {
                $userEntity = new UserEntity();
                $visitor = $this->visitorManager->findOrCreate(null);
                $userEntity->setIdentifier(VisitorIdentifierUtil::encodeIdentifier($visitor->getSessionId()));

                return $userEntity;
            }

            throw OAuthServerException::invalidGrant();
        }

        return parent::getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity);
    }

    #[\Override]
    protected function getUserLoader(ClientEntityInterface $clientEntity): UserLoaderInterface
    {
        if ($this->isFrontendClient($clientEntity)) {
            return $this->frontendUserLoader;
        }

        return parent::getUserLoader($clientEntity);
    }

    private function isFrontendClient(ClientEntityInterface $clientEntity): bool
    {
        return $clientEntity instanceof ClientEntity && $clientEntity->isFrontend();
    }
}
