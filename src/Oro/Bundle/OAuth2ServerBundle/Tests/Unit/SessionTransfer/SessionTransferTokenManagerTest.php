<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\SessionTransferTokenManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use PHPUnit\Framework\TestCase;

class SessionTransferTokenManagerTest extends TestCase
{
    public function testCreateToken(): void
    {
        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $organization = new Organization();
        $organization->setEnabled(true);
        $client = (new Client())
            ->setIdentifier('client-id')
            ->setOrganization($organization)
            ->setSessionTransferAllowed(true);
        $target = new SessionTransferTarget(
            'target_route',
            ['id' => 10],
            ['website_id' => 20]
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(SessionTransferToken::class));
        $entityManager->expects(self::once())->method('flush');
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(SessionTransferToken::class)
            ->willReturn($entityManager);
        $manager = new SessionTransferTokenManager($doctrine);

        $issuedToken = $manager->createToken(
            $client,
            'access-token-id',
            'user@example.com',
            $target,
            new \DateInterval('PT60S')
        );
        $finishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $createdAt = $issuedToken->getEntity()->getCreatedAt();

        self::assertStringStartsWith('stt_', $issuedToken->getToken());
        self::assertSame(60, $issuedToken->getExpiresIn());
        self::assertSame(hash('sha256', $issuedToken->getToken()), $issuedToken->getEntity()->getIdentifier());
        self::assertSame($client, $issuedToken->getEntity()->getClient());
        self::assertSame('access-token-id', $issuedToken->getEntity()->getSourceAccessTokenIdentifier());
        self::assertSame('user@example.com', $issuedToken->getEntity()->getUserIdentifier());
        self::assertSame($organization, $issuedToken->getEntity()->getOrganization());
        self::assertSame('target_route', $issuedToken->getEntity()->getRoute());
        self::assertSame(['id' => 10], $issuedToken->getEntity()->getRouteParameters());
        self::assertSame(['website_id' => 20], $issuedToken->getEntity()->getContextData());
        self::assertGreaterThanOrEqual($startedAt, $createdAt);
        self::assertLessThanOrEqual($finishedAt, $createdAt);
        self::assertEquals($createdAt->modify('+60 seconds'), $issuedToken->getEntity()->getExpiresAt());
        self::assertEquals($createdAt, $client->getLastUsedAt());
    }

    public function testCreateTokenRejectsDisabledCapability(): void
    {
        $client = (new Client())->setOrganization(new Organization());
        $manager = new SessionTransferTokenManager($this->createMock(ManagerRegistry::class));

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10);

        $manager->createToken(
            $client,
            'access-token-id',
            'user@example.com',
            new SessionTransferTarget('target_route', []),
            new \DateInterval('PT60S')
        );
    }
}
