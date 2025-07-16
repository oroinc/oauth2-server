<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendUserRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class FrontendUserRepositoryTest extends TestCase
{
    private UserLoaderInterface&MockObject $userLoader;
    private UserLoaderInterface&MockObject $frontendUserLoader;
    private PasswordHasherFactoryInterface&MockObject $passwordHasherFactory;
    private OAuthUserChecker&MockObject $userChecker;
    private CustomerVisitorManager&MockObject $visitorManager;
    private FrontendUserRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->frontendUserLoader = $this->createMock(UserLoaderInterface::class);
        $this->passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->userChecker = $this->createMock(OAuthUserChecker::class);
        $this->visitorManager = $this->createMock(CustomerVisitorManager::class);

        $this->repository = new FrontendUserRepository(
            $this->userLoader,
            $this->passwordHasherFactory,
            $this->userChecker,
            $this->frontendUserLoader,
            $this->visitorManager
        );
    }

    public function testNotFrontendUser(): void
    {
        $client = new ClientEntity();

        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';

        $this->userLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn(null);
        $this->frontendUserLoader->expects(self::never())
            ->method('loadUser');

        $userEntity = $this->repository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            $client
        );

        self::assertNull($userEntity);
    }

    public function testFrontendUserNotFound(): void
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';

        $this->frontendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn(null);
        $this->userLoader->expects(self::never())
            ->method('loadUser');

        $userEntity = $this->repository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            $client
        );

        self::assertNull($userEntity);
    }

    public function testInvalidPasswordForFrontendUser(): void
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';
        $user = $this->createMock(User::class);
        $userEncodedPassword = 'user_encoded_password';
        $userPasswordSalt = 'user_password_salt';
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $this->frontendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);
        $this->userLoader->expects(self::never())
            ->method('loadUser');
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
            ->method('getUserIdentifier');
        $passwordHasher->expects(self::once())
            ->method('verify')
            ->with($userEncodedPassword, $password, $userPasswordSalt)
            ->willReturn(false);

        $userEntity = $this->repository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            $client
        );

        self::assertNull($userEntity);
    }

    public function testValidPasswordForFrontendUser(): void
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $username = 'test_username';
        $password = 'test_password';
        $grantType = 'test_grant';
        $user = $this->createMock(User::class);
        $userEncodedPassword = 'user_encoded_password';
        $userPasswordSalt = 'user_password_salt';
        $userIdentifier = 'user_identifier';
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $this->frontendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($username)
            ->willReturn($user);
        $this->userLoader->expects(self::never())
            ->method('loadUser');
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

        $userEntity = $this->repository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            $client
        );

        self::assertInstanceOf(UserEntity::class, $userEntity);
        self::assertEquals($userIdentifier, $userEntity->getIdentifier());
    }

    public function testCustomerVisitor(): void
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $visitor = new CustomerVisitor();
        ReflectionUtil::setId($visitor, 234);
        $visitor->setSessionId('testSession');

        $this->visitorManager->expects(self::once())
            ->method('findOrCreate')
            ->with(self::isNull())
            ->willReturn($visitor);
        $this->frontendUserLoader->expects(self::never())
            ->method('loadUser');
        $this->userLoader->expects(self::never())
            ->method('loadUser');

        $expectedEntity = new UserEntity();
        $expectedEntity->setIdentifier('visitor:testSession');

        $this->assertEquals(
            $expectedEntity,
            $this->repository->getUserEntityByUserCredentials('guest', 'guest', 'password', $client)
        );
    }

    public function testCustomerVisitorOnNotPasswordGrantType(): void
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $this->expectException(OAuthServerException::class);

        $this->repository->getUserEntityByUserCredentials('guest', 'guest', 'test', $client);
    }
}
