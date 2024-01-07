<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * The implementation of the user entity repository for "league/oauth2-server" library.
 */
class UserRepository implements UserRepositoryInterface
{
    /** @var UserLoaderInterface */
    private $userLoader;

    /** @var PasswordHasherFactoryInterface */
    private $passwordHasherFactory;

    /** @var OAuthUserChecker */
    private $userChecker;

    public function __construct(
        UserLoaderInterface $userLoader,
        PasswordHasherFactoryInterface $passwordHasherFactory,
        OAuthUserChecker $userChecker
    ) {
        $this->userLoader = $userLoader;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userChecker = $userChecker;
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
        $user = $this->getUserLoader($clientEntity)->loadUser($username);
        if (null === $user) {
            return null;
        }

        $this->userChecker->checkUser($user);

        if (!$this->passwordHasherVerify($user, $password)) {
            return null;
        }

        $userEntity = new UserEntity();
        $userEntity->setIdentifier($user->getUserIdentifier());

        return $userEntity;
    }

    protected function getUserLoader(ClientEntityInterface $clientEntity): UserLoaderInterface
    {
        return $this->userLoader;
    }

    private function passwordHasherVerify(UserInterface $user, string $password): bool
    {
        return $this->passwordHasherFactory
            ->getPasswordHasher($user::class)
            ->verify($user->getPassword(), $password, $user->getSalt());
    }
}
