<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * The implementation of the user entity repository that can handle storefront users.
 */
class FrontendUserRepository extends UserRepository
{
    private const VISITOR_USERNAME = 'guest';
    private const VISITOR_PASSWORD = 'guest';

    /** @var UserLoaderInterface */
    private $frontendUserLoader;

    /** @var CustomerVisitorManager */
    private $customerVisitorManager;

    public function __construct(
        UserLoaderInterface $userLoader,
        EncoderFactoryInterface $encoderFactory,
        OAuthUserChecker $userChecker,
        UserLoaderInterface $frontendUserLoader,
        CustomerVisitorManager $customerVisitorManager
    ) {
        parent::__construct($userLoader, $encoderFactory, $userChecker);
        $this->frontendUserLoader = $frontendUserLoader;
        $this->customerVisitorManager = $customerVisitorManager;
    }

    /**
     * {@inheritdoc}
     */
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
                $visitor = $this->customerVisitorManager->findOrCreate();
                $userEntity->setIdentifier(
                    VisitorIdentifierUtil::encodeIdentifier($visitor->getId(), $visitor->getSessionId())
                );

                return $userEntity;
            }

            throw OAuthServerException::invalidGrant();
        }

        return parent::getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity);
    }

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
