<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;

/**
 * The cleaner that removes outdated OAuth 2.0 access tokens.
 */
class AccessTokenCleaner
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
            ->delete(AccessToken::class, 'e')
            ->where('e.expiresAt < :now')
            ->setParameter('now', new \DateTime('now', new \DateTimeZone('UTC')), Type::DATETIME);

        $refreshTokenSubquery = $this->getEntityManager()->createQueryBuilder()
            ->from(RefreshToken::class, 'r')
            ->select('r')
            ->where('r.accessToken = e AND r.expiresAt >= :now');
        $qb->andWhere($qb->expr()->not($qb->expr()->exists($refreshTokenSubquery->getDQL())));

        $qb->getQuery()->execute();
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(AccessToken::class);
    }
}
