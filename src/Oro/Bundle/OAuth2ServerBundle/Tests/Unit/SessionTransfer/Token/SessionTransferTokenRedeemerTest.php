<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Token;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Token\SessionTransferTokenRedeemer;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class SessionTransferTokenRedeemerTest extends TestCase
{
    public function testRedeem(): void
    {
        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $plainToken = 'stt_plain-token';
        $transferToken = $this->createTransferToken(hash('sha256', $plainToken));
        $entityManager = $this->createEntityManager($transferToken, 1);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(SessionTransferToken::class)
            ->willReturn($entityManager);
        $result = (new SessionTransferTokenRedeemer($doctrine))->redeem($plainToken);
        $finishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertSame($transferToken, $result);
        self::assertGreaterThanOrEqual($startedAt, $transferToken->getConsumedAt());
        self::assertLessThanOrEqual($finishedAt, $transferToken->getConsumedAt());
    }

    public function testRedeemRejectsEmptyToken(): void
    {
        $redeemer = new SessionTransferTokenRedeemer($this->createMock(ManagerRegistry::class));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('missing');

        $redeemer->redeem('');
    }

    public function testRedeemRejectsInvalidPrefix(): void
    {
        $redeemer = new SessionTransferTokenRedeemer($this->createMock(ManagerRegistry::class));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('invalid format');

        $redeemer->redeem('plain-token');
    }

    public function testRedeemRejectsUnknownToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->willReturn(null);
        $entityManager->expects(self::once())->method('getRepository')->willReturn($repository);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entityManager);
        $redeemer = new SessionTransferTokenRedeemer($doctrine);

        $this->expectException(GoneHttpException::class);
        $this->expectExceptionMessage('invalid or has expired');

        $redeemer->redeem('stt_unknown');
    }

    public function testRedeemRejectsAlreadyConsumedToken(): void
    {
        $transferToken = $this->createTransferToken(hash('sha256', 'stt_plain-token'));
        $entityManager = $this->createEntityManager($transferToken, 0);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entityManager);
        $redeemer = new SessionTransferTokenRedeemer($doctrine);

        $this->expectException(GoneHttpException::class);
        $this->expectExceptionMessage('already been used');

        $redeemer->redeem('stt_plain-token');
    }

    private function createTransferToken(string $identifier): SessionTransferToken
    {
        $organization = new Organization();
        $organization->setEnabled(true);
        $client = (new Client())
            ->setOrganization($organization)
            ->setSessionTransferAllowed(true);
        $transferToken = (new SessionTransferToken())
            ->setIdentifier($identifier)
            ->setClient($client)
            ->setOrganization($organization)
            ->setExpiresAt(new \DateTime('+1 minute'));
        ReflectionUtil::setId($transferToken, 42);

        return $transferToken;
    }

    private function createEntityManager(SessionTransferToken $transferToken, int $affectedRows): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $transferToken->getIdentifier()])
            ->willReturn($transferToken);
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('execute')->willReturn($affectedRows);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('update')->willReturnSelf();
        $queryBuilder->method('set')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        return $entityManager;
    }
}
