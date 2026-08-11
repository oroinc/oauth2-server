<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\SessionTransferSubjectResolver;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class SessionTransferSubjectResolverTest extends TestCase
{
    public function testResolveUserIdentifierFromAccessToken(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::never())->method('getManagerForClass');
        $accessToken = new AccessToken('access-token', new \DateTime('+1 hour'), [], new Client(), 'user@example.com');

        self::assertEquals(
            'user@example.com',
            (new SessionTransferSubjectResolver($doctrine))->resolveUserIdentifier($accessToken)
        );
    }

    public function testResolveUserIdentifierFromBackendClientOwner(): void
    {
        $owner = new User();
        $owner->setUsername('owner@example.com');
        $client = new Client();
        $client->setOwnerEntity(User::class, 42);
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('find')->with(42)->willReturn($owner);
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('getRepository')->with(User::class)->willReturn($repository);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::once())->method('getManagerForClass')->with(User::class)->willReturn($manager);
        $accessToken = new AccessToken('access-token', new \DateTime('+1 hour'), [], $client);

        self::assertSame(
            'owner@example.com',
            (new SessionTransferSubjectResolver($doctrine))->resolveUserIdentifier($accessToken)
        );
    }

    public function testResolveEmptyUserIdentifierFromBackendClientOwner(): void
    {
        $owner = new User();
        $owner->setUsername('');
        $client = new Client();
        $client->setOwnerEntity(User::class, 42);
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('find')->with(42)->willReturn($owner);
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('getRepository')->with(User::class)->willReturn($repository);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::once())->method('getManagerForClass')->with(User::class)->willReturn($manager);
        $accessToken = new AccessToken('access-token', new \DateTime('+1 hour'), [], $client);

        self::assertSame(
            '',
            (new SessionTransferSubjectResolver($doctrine))->resolveUserIdentifier($accessToken)
        );
    }

    public function testResolveUserIdentifierFailsWhenClientHasNoOwner(): void
    {
        $accessToken = new AccessToken('access-token', new \DateTime('+1 hour'), [], new Client());
        $resolver = new SessionTransferSubjectResolver($this->createMock(ManagerRegistry::class));

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10);

        $resolver->resolveUserIdentifier($accessToken);
    }
}
