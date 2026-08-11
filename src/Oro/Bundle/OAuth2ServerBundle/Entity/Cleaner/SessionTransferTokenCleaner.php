<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;

/**
 * Removes expired Session Transfer Tokens.
 */
final class SessionTransferTokenCleaner
{
    public function __construct(
        private readonly ManagerRegistry $doctrine
    ) {
    }

    public function cleanUp(): void
    {
        $now = \DateTime::createFromImmutable(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $this->getEntityManager()
            ->createQueryBuilder()
            ->delete(SessionTransferToken::class, 'e')
            ->where('e.expiresAt <= :now')
            ->setParameter('now', $now, Types::DATETIME_MUTABLE)
            ->getQuery()
            ->execute();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(SessionTransferToken::class);
    }
}
