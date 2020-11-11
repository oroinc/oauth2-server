<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;

/**
 * The cleaner that removes outdated OAuth 2.0 auth codes.
 */
class AuthCodeCleaner
{
    /** @var ManagerRegistry */
    private $doctrine;

    /**
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * Removes all outdated OAuth 2.0 access tokens.
     */
    public function cleanUp(): void
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->delete(AuthCode::class, 'e')
            ->where('e.expiresAt < :now')
            ->setParameter('now', new \DateTime('now', new \DateTimeZone('UTC')), Types::DATETIME_MUTABLE);
        $qb->getQuery()->execute();
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(AuthCode::class);
    }
}