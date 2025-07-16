<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\AccessTokenRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AccessTokenRepositoryTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private ClientManager&MockObject $clientManager;
    private AccessTokenRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->clientManager = $this->createMock(ClientManager::class);

        $this->repository = new AccessTokenRepository($this->doctrine, $this->clientManager);
    }

    private function expectGetEntityManager(): EntityManagerInterface&MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeast(1))
            ->method('getManagerForClass')
            ->with(AccessToken::class)
            ->willReturn($em);

        return $em;
    }

    private function expectGetRepository(
        string $entityClass,
        EntityManagerInterface|MockObject|null $em = null
    ): EntityRepository&MockObject {
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

    public function testGetNewToken(): void
    {
        $client = new ClientEntity();
        $scope1 = new ScopeEntity();
        $scope1->setIdentifier('test_scope1');
        $scope2 = new ScopeEntity();
        $scope2->setIdentifier('test_scope2');
        $scopes = [$scope1, $scope2];
        $userIdentifier = 'user_identifier';
        $accessToken = $this->repository->getNewToken($client, $scopes, $userIdentifier);
        self::assertInstanceOf(AccessTokenEntity::class, $accessToken);
        self::assertSame($client, $accessToken->getClient());
        self::assertSame($scopes, $accessToken->getScopes());
        self::assertSame($userIdentifier, $accessToken->getUserIdentifier());
    }

    public function testGetNewTokenWithoutUserIdentifier(): void
    {
        $client = new ClientEntity();
        $scopes = [];
        $accessToken = $this->repository->getNewToken($client, $scopes);
        self::assertInstanceOf(AccessTokenEntity::class, $accessToken);
        self::assertSame($client, $accessToken->getClient());
        self::assertSame($scopes, $accessToken->getScopes());
        self::assertNull($accessToken->getUserIdentifier());
    }

    public function testPersistNewAccessTokenOnExistToken(): void
    {
        $this->expectException(UniqueTokenIdentifierConstraintViolationException::class);
        $this->expectExceptionMessage('Could not create unique access token identifier');

        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setIdentifier('test_id');

        $existingToken = new AccessToken('test_id', new \DateTime(), [], new Client());

        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        $this->repository->persistNewAccessToken($accessTokenEntity);
    }

    public function testPersistNewAccessToken(): void
    {
        $scope = new ScopeEntity();
        $scope->setIdentifier('test_scope');
        $expireDate = new \DateTimeImmutable();
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('client_id');
        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setIdentifier('test_id');
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setExpiryDateTime($expireDate);
        $accessTokenEntity->addScope($scope);
        $accessTokenEntity->setUserIdentifier('user_id');

        $client = new Client();
        $client->setIdentifier('client_id');
        $expectedToken = new AccessToken(
            'test_id',
            \DateTime::createFromImmutable($expireDate),
            ['test_scope'],
            $client,
            'user_id'
        );

        $em = $this->expectGetEntityManager();
        $accessTokenRepository = $this->createMock(EntityRepository::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with(AccessToken::class)
            ->willReturn($accessTokenRepository);

        $accessTokenRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('client_id')
            ->willReturn($client);
        $em->expects(self::exactly(2))
            ->method('persist')
            ->withConsecutive(
                [$expectedToken],
                [self::identicalTo($client)]
            );
        $em->expects(self::once())
            ->method('flush');

        $this->repository->persistNewAccessToken($accessTokenEntity);

        self::assertNotNull($client->getLastUsedAt());
    }

    public function testRevokeAccessTokenOnNonExistToken(): void
    {
        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        $this->repository->revokeAccessToken('test_id');
    }

    public function testRevokeAccessTokenOnExistToken(): void
    {
        $existingToken = new AccessToken('test_id', new \DateTime(), [], new Client());

        $em = $this->expectGetEntityManager();
        $repository = $this->expectGetRepository(AccessToken::class, $em);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);
        $em->expects(self::once())
            ->method('persist')
            ->with($existingToken);
        $em->expects(self::once())
            ->method('flush');

        $this->repository->revokeAccessToken('test_id');
        self::assertTrue($existingToken->isRevoked());
    }

    public function testIsAccessTokenRevokedOnNonExistingToken(): void
    {
        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        self::assertTrue($this->repository->isAccessTokenRevoked('test_id'));
    }

    public function testIsAccessTokenRevokedOnNonExistingRevokedToken(): void
    {
        $existingToken = new AccessToken('test_id', new \DateTime(), [], new Client());
        $existingToken->revoke();

        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        self::assertTrue($this->repository->isAccessTokenRevoked('test_id'));
    }

    public function testIsAccessTokenRevokedOnNonExistingNotRevokedToken(): void
    {
        $existingToken = new AccessToken('test_id', new \DateTime(), [], new Client());

        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn($existingToken);

        self::assertFalse($this->repository->isAccessTokenRevoked('test_id'));
    }
}
