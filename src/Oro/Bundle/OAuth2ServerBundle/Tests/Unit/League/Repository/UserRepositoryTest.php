<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\UserRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

class UserRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var UserLoaderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $userLoader;

    /** @var EncoderFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $encoderFactory;

    /** @var UserRepository */
    private $userRepository;

    protected function setUp(): void
    {
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);

        $this->userRepository = new UserRepository(
            $this->userLoader,
            $this->encoderFactory,
            $this->createMock(OAuthUserChecker::class)
        );
    }

    public function testUserNotFound()
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

    public function testInvalidPassword()
    {
        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';
        $user = $this->createMock(UserInterface::class);
        $userEncodedPassword = 'user_encoded_password';
        $userPasswordSalt = 'user_password_salt';
        $passwordEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($user))
            ->willReturn($passwordEncoder);
        $user->expects(self::once())
            ->method('getPassword')
            ->willReturn($userEncodedPassword);
        $user->expects(self::once())
            ->method('getSalt')
            ->willReturn($userPasswordSalt);
        $user->expects(self::never())
            ->method('getUserName');
        $passwordEncoder->expects(self::once())
            ->method('isPasswordValid')
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

    public function testValidPassword()
    {
        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';
        $user = $this->createMock(UserInterface::class);
        $userEncodedPassword = 'user_encoded_password';
        $userPasswordSalt = 'user_password_salt';
        $userUsername = 'user_username';
        $passwordEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($user))
            ->willReturn($passwordEncoder);
        $user->expects(self::once())
            ->method('getPassword')
            ->willReturn($userEncodedPassword);
        $user->expects(self::once())
            ->method('getSalt')
            ->willReturn($userPasswordSalt);
        $user->expects(self::once())
            ->method('getUserName')
            ->willReturn($userUsername);
        $passwordEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($userEncodedPassword, $password, $userPasswordSalt)
            ->willReturn(true);

        $userEntity = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            new ClientEntity()
        );

        self::assertInstanceOf(UserEntity::class, $userEntity);
        self::assertEquals($userUsername, $userEntity->getIdentifier());
    }
}
