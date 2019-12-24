<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\AccessTokenRepository;

class AccessTokenRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var AccessTokenRepository */
    private $repository;

    protected function setUp()
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->repository = new AccessTokenRepository($this->doctrine);
    }

    /**
     * @return EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function expectGetEntityManager()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeast(1))
            ->method('getManagerForClass')
            ->with(AccessToken::class)
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

    public function testGetNewToken()
    {
        self::assertEquals(new AccessTokenEntity(), $this->repository->getNewToken(new ClientEntity(), []));
    }

    /**
     * @expectedException \League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException
     * @expectedExceptionMessage Could not create unique access token identifier
     */
    public function testPersistNewAccessTokenOnExistToken()
    {
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

    public function testPersistNewAccessToken()
    {
        $scope = new ScopeEntity();
        $scope->setIdentifier('test_scope');
        $expireDate = new \DateTime();
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
        $expectedToken = new AccessToken('test_id', $expireDate, ['test_scope'], $client, 'user_id');

        $em = $this->expectGetEntityManager();
        $accessTokenRepository = $this->createMock(EntityRepository::class);
        $clientRepository = $this->createMock(EntityRepository::class);
        $em->expects(self::exactly(2))
            ->method('getRepository')
            ->willReturnMap([
                [AccessToken::class, $accessTokenRepository],
                [Client::class, $clientRepository]
            ]);

        $accessTokenRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);
        $clientRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'client_id'])
            ->willReturn($client);
        $em->expects(self::at(2))
            ->method('persist')
            ->with($expectedToken);
        $em->expects(self::at(3))
            ->method('persist')
            ->with($client);
        $em->expects(self::once())
            ->method('flush');

        $this->repository->persistNewAccessToken($accessTokenEntity);

        self::assertNotNull($client->getLastUsedAt());
    }

    public function testRevokeAccessTokenOnNonExistToken()
    {
        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        $this->repository->revokeAccessToken('test_id');
    }

    public function testRevokeAccessTokenOnExistToken()
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

    public function testIsAccessTokenRevokedOnNonExistingToken()
    {
        $repository = $this->expectGetRepository(AccessToken::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        self::assertTrue($this->repository->isAccessTokenRevoked('test_id'));
    }

    public function testIsAccessTokenRevokedOnNonExistingRevokedToken()
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

    public function testIsAccessTokenRevokedOnNonExistingNotRevokedToken()
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
