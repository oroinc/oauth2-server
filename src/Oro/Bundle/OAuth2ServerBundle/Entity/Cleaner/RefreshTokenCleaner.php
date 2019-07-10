<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;

/**
 * The cleaner that removes outdated OAuth 2.0 refresh tokens.
 */
class RefreshTokenCleaner
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
            ->delete(RefreshToken::class, 'e')
            ->where('e.expiresAt < :now')
            ->setParameter('now', new \DateTime('now', new \DateTimeZone('UTC')));
        $qb->getQuery()->execute();
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(RefreshToken::class);
    }
}
