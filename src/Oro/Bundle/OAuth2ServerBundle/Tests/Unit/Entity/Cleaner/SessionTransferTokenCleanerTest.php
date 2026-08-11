<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Entity\Cleaner;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner\SessionTransferTokenCleaner;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use PHPUnit\Framework\TestCase;

class SessionTransferTokenCleanerTest extends TestCase
{
    public function testCleanUp(): void
    {
        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $query = $this->createMock(Query::class);
        $query->expects(self::once())
            ->method('execute');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())
            ->method('delete')
            ->with(SessionTransferToken::class, 'e')
            ->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('where')
            ->with('e.expiresAt <= :now')
            ->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('setParameter')
            ->with(
                'now',
                self::callback(
                    static fn (\DateTimeInterface $value): bool =>
                        'UTC' === $value->getTimezone()->getName()
                        && $value >= $startedAt
                        && $value <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                ),
                Types::DATETIME_MUTABLE
            )
            ->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(SessionTransferToken::class)
            ->willReturn($entityManager);

        (new SessionTransferTokenCleaner($doctrine))->cleanUp();
    }
}
