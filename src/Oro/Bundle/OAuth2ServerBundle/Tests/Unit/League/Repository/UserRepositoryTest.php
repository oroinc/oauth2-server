<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\UserRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class UserRepositoryTest extends TestCase
{
    private UserLoaderInterface&MockObject $userLoader;
    private PasswordHasherFactoryInterface&MockObject $passwordHasherFactory;
    private UserRepository $userRepository;

    #[\Override]
    protected function setUp(): void
    {
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);

        $this->userRepository = new UserRepository(
            $this->userLoader,
            $this->passwordHasherFactory,
            $this->createMock(OAuthUserChecker::class)
        );
    }

    public function testUserNotFound(): void
    {
        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn(null);

        $userEntity = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            new ClientEntity()
        );

        self::assertNull($userEntity);
    }

    public function testInvalidPassword(): void
    {
        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';
        $user = $this->createMock(User::class);
        $userEncodedPassword = 'user_encoded_password';
        $userPasswordSalt = 'user_password_salt';
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($user::class)
            ->willReturn($passwordHasher);
        $user->expects(self::once())
            ->method('getPassword')
            ->willReturn($userEncodedPassword);
        $user->expects(self::once())
            ->method('getSalt')
            ->willReturn($userPasswordSalt);
        $user->expects(self::never())
            ->method('getUserName');
        $passwordHasher->expects(self::once())
            ->method('verify')
            ->with($userEncodedPassword, $password, $userPasswordSalt)
            ->willReturn(false);

        $userEntity = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            new ClientEntity()
        );

        self::assertNull($userEntity);
    }

    public function testValidPassword(): void
    {
        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';
        $user = $this->createMock(User::class);
        $userEncodedPassword = 'user_encoded_password';
        $userPasswordSalt = 'user_password_salt';
        $userIdentifier = 'user_identifier';
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);
        $this->passwordHasherFactory->expects(self::once())
            ->method('getPasswordHasher')
            ->with($user::class)
            ->willReturn($passwordHasher);
        $user->expects(self::once())
            ->method('getPassword')
            ->willReturn($userEncodedPassword);
        $user->expects(self::once())
            ->method('getSalt')
            ->willReturn($userPasswordSalt);
        $user->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn($userIdentifier);
        $passwordHasher->expects(self::once())
            ->method('verify')
            ->with($userEncodedPassword, $password, $userPasswordSalt)
            ->willReturn(true);

        $userEntity = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            new ClientEntity()
        );

        self::assertInstanceOf(UserEntity::class, $userEntity);
        self::assertEquals($userIdentifier, $userEntity->getIdentifier());
    }
}
