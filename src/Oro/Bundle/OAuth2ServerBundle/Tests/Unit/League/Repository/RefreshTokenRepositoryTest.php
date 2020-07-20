<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\RefreshTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\RefreshTokenRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

class RefreshTokenRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var UserLoaderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $userLoader;

    /** @var OAuthUserChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $userChecker;

    /** @var RefreshTokenRepository */
    private $repository;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->userChecker = $this->createMock(OAuthUserChecker::class);

        $this->repository = new RefreshTokenRepository(
            $this->doctrine,
            $this->userLoader,
            $this->userChecker
        );
    }

    /**
     * @return EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function expectGetEntityManager()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeast(1))
            ->method('getManagerForClass')
            ->with(RefreshToken::class)
            ->willReturn($em);

        return $em;
    }

    /**
     * @param string                                                               $entityClass
     * @param EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject|null $em
     *
     * @return EntityRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private function expectGetRepository($entityClass, $em = null)
    {
        if (null === $em) {
            $em = $this->expectGetEntityManager();
        }

        $repository = $this->createMock(EntityRepository::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with($entityClass)
            ->willReturn($repository);

        return $repository;
    }

    public function testGetNewRefreshToken()
    {
        self::assertInstanceOf(RefreshTokenEntity::class, $this->repository->getNewRefreshToken());
    }

    public function testPersistNewRefreshTokenOnExistToken()
    {
        $this->expectException(UniqueTokenIdentifierConstraintViolationException::class);
        $this->expectExceptionMessage('Could not create unique access token identifier');

        $refreshTokenEntity = new RefreshTokenEntity();
        $refreshTokenEntity->setIdentifier('test_id');

        $accessToken = new AccessToken('test_id', new \DateTime(), ['test_scope'], new Client(), 'user_id');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);

        $repository = $this->expectGetRepository(RefreshToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);
        $this->userLoader->expects($this->once())
            ->method('loadUser')
            ->with('user_id')
            ->willReturn($this->createMock(UserInterface::class));

        $this->repository->persistNewRefreshToken($refreshTokenEntity);
    }

    public function testPersistNewAccessToken()
    {
        $scope = new ScopeEntity();
        $scope->setIdentifier('test_scope');
        $expireDate = new \DateTimeImmutable();
        $expireRefreshTokenDate = new \DateTimeImmutable('2120-01-01');
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('client_id');
        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setIdentifier('test_id');
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setExpiryDateTime($expireDate);
        $accessTokenEntity->addScope($scope);
        $accessTokenEntity->setUserIdentifier('user_id');
        $refreshTokenEntity = new RefreshTokenEntity();
        $refreshTokenEntity->setIdentifier('refresh_token_id');
        $refreshTokenEntity->setAccessToken($accessTokenEntity);
        $refreshTokenEntity->setExpiryDateTime($expireRefreshTokenDate);

        $client = new Client();
        $client->setIdentifier('client_id');
        $accessToken = new AccessToken(
            'test_id',
            \DateTime::createFromImmutable($expireDate),
            ['test_scope'],
            $client,
            'user_id'
        );
        $expectedRefreshToken = new RefreshToken(
            'refresh_token_id',
            \DateTime::createFromImmutable($expireRefreshTokenDate),
            $accessToken
        );

        $em = $this->expectGetEntityManager();
        $accessTokenRepository = $this->createMock(EntityRepository::class);
        $refreshTokenRepository = $this->createMock(EntityRepository::class);
        $em->expects(self::exactly(2))
            ->method('getRepository')
            ->willReturnMap([
                [AccessToken::class, $accessTokenRepository],
                [RefreshToken::class, $refreshTokenRepository]
            ]);

        $accessTokenRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($accessToken);
        $refreshTokenRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'refresh_token_id'])
            ->willReturn(null);
        $em->expects(self::once())
            ->method('persist')
            ->with($expectedRefreshToken);
        $em->expects(self::once())
            ->method('flush');

        $this->repository->persistNewRefreshToken($refreshTokenEntity);
    }

    public function testRevokeRefreshTokenOnNonExistToken()
    {
        $repository = $this->expectGetRepository(RefreshToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        $this->repository->revokeRefreshToken('test_id');
    }

    public function testRevokeRefreshTokenOnExistToken()
    {
        $accessToken = new AccessToken('test_id', new \DateTime(), ['test_scope'], new Client(), 'user_id');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $this->userLoader->expects($this->once())
            ->method('loadUser')
            ->with('user_id')
            ->willReturn($this->createMock(UserInterface::class));

        $em = $this->expectGetEntityManager();
        $repository = $this->expectGetRepository(RefreshToken::class, $em);
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

    public function testRevokeRefreshTokenOnExistTokenAndAccessTokenWithoutUserIdentifier()
    {
        $accessToken = new AccessToken('test_id', new \DateTime(), ['test_scope'], new Client());
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);

        $em = $this->expectGetEntityManager();
        $repository = $this->expectGetRepository(RefreshToken::class, $em);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        $this->expectException(OAuthServerException::class);

        $this->repository->revokeRefreshToken('test_id');
    }

    public function testIsRefreshTokenRevokedOnNonExistingToken()
    {
        $repository = $this->expectGetRepository(RefreshToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        self::assertTrue($this->repository->isRefreshTokenRevoked('test_id'));
    }

    public function testIsRefreshTokenRevokedOnNonExistingRevokedToken()
    {
        $accessToken = new AccessToken('test_id', new \DateTime(), ['test_scope'], new Client(), 'user_id');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $this->userLoader->expects($this->once())
            ->method('loadUser')
            ->with('user_id')
            ->willReturn($this->createMock(UserInterface::class));
        $existingToken->revoke();

        $repository = $this->expectGetRepository(RefreshToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        self::assertTrue($this->repository->isRefreshTokenRevoked('test_id'));
    }

    public function testIsRefreshTokenRevokedOnNonExistingNotRevokedToken()
    {
        $accessToken = new AccessToken('test_id', new \DateTime(), ['test_scope'], new Client(), 'user_id');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $this->userLoader->expects($this->once())
            ->method('loadUser')
            ->with('user_id')
            ->willReturn($this->createMock(UserInterface::class));

        $repository = $this->expectGetRepository(RefreshToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        self::assertFalse($this->repository->isRefreshTokenRevoked('test_id'));
    }

    public function testIsRefreshTokenRevokedOnNonExistingNotRevokedTokenForNotValidUser()
    {
        $accessToken = new AccessToken('test_auth', new \DateTime(), [], $this->createMock(Client::class), 'user_id');
        $existingToken = new RefreshToken('test_id', new \DateTime(), $accessToken);
        $user = $this->createMock(UserInterface::class);

        $this->userLoader->expects($this->once())
            ->method('loadUser')
            ->with('user_id')
            ->willReturn($user);
        $this->userChecker->expects($this->once())
            ->method('checkUser')
            ->with($user)
            ->willThrowException(OAuthServerException::invalidGrant());

        $repository = $this->expectGetRepository(RefreshToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10); // invalid grant

        self::assertFalse($this->repository->isRefreshTokenRevoked('test_id'));
    }
}
