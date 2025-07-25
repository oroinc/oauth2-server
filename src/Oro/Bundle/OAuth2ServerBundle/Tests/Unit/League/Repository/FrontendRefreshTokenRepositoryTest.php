<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendRefreshTokenRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FrontendRefreshTokenRepositoryTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private UserLoaderInterface&MockObject $userLoader;
    private OAuthUserChecker&MockObject $userChecker;
    private UserLoaderInterface $frontendUserLoader;
    private FrontendRefreshTokenRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->frontendUserLoader = $this->createMock(UserLoaderInterface::class);
        $this->userChecker = $this->createMock(OAuthUserChecker::class);

        $this->repository = new FrontendRefreshTokenRepository(
            $this->doctrine,
            $this->userLoader,
            $this->userChecker,
            $this->frontendUserLoader
        );
    }

    public function testRevokeRefreshTokenOnExistTokenAndFrontendClient(): void
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

    public function testRevokeRefreshTokenOnExistTokenAndFrontendClientWithVisitorIdentifier(): void
    {
        $client = new Client();
        $client->setFrontend(true);
        $accessToken = new AccessToken('at_test_id', new \DateTime(), ['test_scope'], $client, 'visitor:test');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
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
}
