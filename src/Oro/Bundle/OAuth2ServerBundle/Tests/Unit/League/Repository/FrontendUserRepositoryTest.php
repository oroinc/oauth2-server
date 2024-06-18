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
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class FrontendUserRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var UserLoaderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $userLoader;

    /** @var UserLoaderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $frontendUserLoader;

    /** @var PasswordHasherFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $passwordHasherFactory;

    /** @var OAuthUserChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $userChecker;

    /** @var CustomerVisitorManager|\PHPUnit\Framework\MockObject\MockObject */
    private $customerVisitorManager;

    /** @var FrontendUserRepository */
    private $repository;

    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }

        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->frontendUserLoader = $this->createMock(UserLoaderInterface::class);
        $this->passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->userChecker = $this->createMock(OAuthUserChecker::class);
        $this->customerVisitorManager = $this->createMock(CustomerVisitorManager::class);

        $this->repository = new FrontendUserRepository(
            $this->userLoader,
            $this->passwordHasherFactory,
            $this->userChecker,
            $this->frontendUserLoader,
            $this->customerVisitorManager
        );
    }

    public function testNotFrontendUser()
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

    public function testFrontendUserNotFound()
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

    public function testInvalidPasswordForFrontendUser()
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

    public function testValidPasswordForFrontendUser()
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

    public function testCustomerVisitor()
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $visitor = new CustomerVisitor();
        ReflectionUtil::setId($visitor, 234);
        $visitor->setSessionId('testSession');

        $this->customerVisitorManager->expects(self::once())
            ->method('findOrCreate')
            ->willReturn($visitor);
        $this->frontendUserLoader->expects(self::never())
            ->method('loadUser');
        $this->userLoader->expects(self::never())
            ->method('loadUser');

        $expectedEntity = new UserEntity();
        $expectedEntity->setIdentifier('visitor:234:testSession');

        $this->assertEquals(
            $expectedEntity,
            $this->repository->getUserEntityByUserCredentials('guest', 'guest', 'password', $client)
        );
    }

    public function testCustomerVisitorOnNotPasswordGrantType()
    {
        $client = new ClientEntity();
        $client->setFrontend(true);

        $this->expectException(OAuthServerException::class);

        $this->repository->getUserEntityByUserCredentials('guest', 'guest', 'test', $client);
    }
}
