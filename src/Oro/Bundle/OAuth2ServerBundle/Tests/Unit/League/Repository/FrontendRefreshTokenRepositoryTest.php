<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendRefreshTokenRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

class FrontendRefreshTokenRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var UserLoaderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $userLoader;

    /** @var OAuthUserChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $userChecker;

    /** @var UserLoaderInterface */
    private $frontendUserLoader;

    /** @var CustomerVisitorManager */
    private $customerVisitorManager;

    /** @var FrontendRefreshTokenRepository */
    private $repository;

    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }

        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->frontendUserLoader = $this->createMock(UserLoaderInterface::class);
        $this->userChecker = $this->createMock(OAuthUserChecker::class);
        $this->customerVisitorManager = $this->createMock('Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager');

        $this->repository = new FrontendRefreshTokenRepository(
            $this->doctrine,
            $this->userLoader,
            $this->userChecker,
            $this->frontendUserLoader,
            $this->customerVisitorManager
        );
    }

    public function testRevokeRefreshTokenOnExistTokenAndFrontendClient()
    {
        $client = new Client();
        $client->setFrontend(true);
        $accessToken = new AccessToken('test_id', new \DateTime(), ['test_scope'], $client, 'user_id');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $this->frontendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with('user_id')
            ->willReturn($this->createMock(UserInterface::class));
        $this->userChecker->expects(self::once())
            ->method('checkUser');

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeast(1))
            ->method('getManagerForClass')
            ->with(RefreshToken::class)
            ->willReturn($em);

        $repository = $this->createMock(EntityRepository::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with(RefreshToken::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);
        $em->expects(self::once())
            ->method('persist')
            ->with($existingToken);
        $em->expects(self::once())
            ->method('flush');

        $this->repository->revokeRefreshToken('test_id');
        self::assertTrue($existingToken->isRevoked());
    }

    public function testRevokeRefreshTokenOnExistTokenAndFrontendClientWithVisitorIdentifier()
    {
        $client = new Client();
        $client->setFrontend(true);
        $accessToken = new AccessToken('at_test_id', new \DateTime(), ['test_scope'], $client, 'visitor:123:test');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $this->customerVisitorManager->expects(self::once())
            ->method('find')
            ->with(123, 'test')
            ->willReturn(new \stdClass());
        $this->userChecker->expects(self::never())
            ->method('checkUser');

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeast(1))
            ->method('getManagerForClass')
            ->with(RefreshToken::class)
            ->willReturn($em);

        $repository = $this->createMock(EntityRepository::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with(RefreshToken::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);
        $em->expects(self::once())
            ->method('persist')
            ->with($existingToken);
        $em->expects(self::once())
            ->method('flush');

        $this->repository->revokeRefreshToken('test_id');
        self::assertTrue($existingToken->isRevoked());
    }

    public function testRevokeRefreshTokenOnExistTokenAndFrontendClientWithWrongVisitorIdentifier()
    {
        $client = new Client();
        $client->setFrontend(true);
        $accessToken = new AccessToken('at_test_id', new \DateTime(), ['test_scope'], $client, 'visitor:123:test');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $this->customerVisitorManager->expects(self::once())
            ->method('find')
            ->with(123, 'test')
            ->willReturn(null);
        $this->userChecker->expects(self::never())
            ->method('checkUser');

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeast(1))
            ->method('getManagerForClass')
            ->with(RefreshToken::class)
            ->willReturn($em);

        $repository = $this->createMock(EntityRepository::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with(RefreshToken::class)
            ->willReturn($repository);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);
        $em->expects(self::never())
            ->method('persist');
        $em->expects(self::never())
            ->method('flush');

        $this->expectException(OAuthServerException::class);

        $this->repository->revokeRefreshToken('test_id');
    }
}
