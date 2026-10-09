<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendAccessTokenRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\ClientOwnerScopeValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FrontendAccessTokenRepositoryTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private ClientManager&MockObject $clientManager;
    private ClientOwnerScopeValidator&MockObject $clientOwnerScopeValidator;
    private FrontendAccessTokenRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->clientOwnerScopeValidator = $this->createMock(ClientOwnerScopeValidator::class);
        $this->repository = new FrontendAccessTokenRepository(
            $this->doctrine,
            $this->clientManager,
            $this->clientOwnerScopeValidator
        );
    }

    public function testPersistNewAccessTokenWhenUserIsOutsideOfClientOwnerScope(): void
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('client_id');
        $clientEntity->setFrontend(true);
        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setUserIdentifier('user_id');

        $client = new Client();
        $client->setIdentifier('client_id');
        $client->setFrontend(true);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('client_id')
            ->willReturn($client);
        $this->clientOwnerScopeValidator->expects(self::once())
            ->method('isValid')
            ->with(self::identicalTo($client), 'user_id')
            ->willReturn(false);
        $this->doctrine->expects(self::never())
            ->method('getManagerForClass');

        $this->expectException(OAuthServerException::class);

        $this->repository->persistNewAccessToken($accessTokenEntity);
    }

    public function testPersistNewAccessTokenWhenUserIsWithinClientOwnerScope(): void
    {
        $expiresAt = new \DateTimeImmutable();
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('client_id');
        $clientEntity->setFrontend(true);
        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setIdentifier('test_id');
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setUserIdentifier('user_id');
        $accessTokenEntity->setExpiryDateTime($expiresAt);

        $client = new Client();
        $client->setIdentifier('client_id');
        $client->setFrontend(true);
        $this->clientManager->expects(self::exactly(2))
            ->method('getClient')
            ->with('client_id')
            ->willReturn($client);
        $this->clientOwnerScopeValidator->expects(self::once())
            ->method('isValid')
            ->with(self::identicalTo($client), 'user_id')
            ->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeastOnce())
            ->method('getManagerForClass')
            ->with(AccessToken::class)
            ->willReturn($entityManager);
        $accessTokenRepository = $this->createMock(EntityRepository::class);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(AccessToken::class)
            ->willReturn($accessTokenRepository);
        $accessTokenRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        $expectedAccessToken = new AccessToken(
            'test_id',
            \DateTime::createFromImmutable($expiresAt),
            [],
            $client,
            'user_id'
        );
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->withConsecutive(
                [$expectedAccessToken],
                [self::identicalTo($client)]
            );
        $entityManager->expects(self::once())
            ->method('flush');

        $this->repository->persistNewAccessToken($accessTokenEntity);
    }

    public function testPersistNewAccessTokenForBackendClientDoesNotValidateOwnerScope(): void
    {
        $expiresAt = new \DateTimeImmutable();
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('client_id');
        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setIdentifier('test_id');
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setUserIdentifier('user_id');
        $accessTokenEntity->setExpiryDateTime($expiresAt);

        $client = new Client();
        $client->setIdentifier('client_id');
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('client_id')
            ->willReturn($client);
        $this->clientOwnerScopeValidator->expects(self::never())
            ->method('isValid');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->expects(self::atLeastOnce())
            ->method('getManagerForClass')
            ->with(AccessToken::class)
            ->willReturn($entityManager);
        $accessTokenRepository = $this->createMock(EntityRepository::class);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(AccessToken::class)
            ->willReturn($accessTokenRepository);
        $accessTokenRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_id'])
            ->willReturn(null);

        $expectedAccessToken = new AccessToken(
            'test_id',
            \DateTime::createFromImmutable($expiresAt),
            [],
            $client,
            'user_id'
        );
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->withConsecutive(
                [$expectedAccessToken],
                [self::identicalTo($client)]
            );
        $entityManager->expects(self::once())
            ->method('flush');

        $this->repository->persistNewAccessToken($accessTokenEntity);

        self::assertNotNull($client->getLastUsedAt());
    }
}
