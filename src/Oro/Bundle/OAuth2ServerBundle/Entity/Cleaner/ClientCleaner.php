<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

/**
 * The cleaner that removes OAuth 2.0 clients that belong to removed users.
 */
class ClientCleaner
{
    /** @var string[] */
    private $ownerEntityClasses;

    /** @var ManagerRegistry */
    private $doctrine;

    /**
     * @param string[]        $ownerEntityClasses
     * @param ManagerRegistry $doctrine
     */
    public function __construct(array $ownerEntityClasses, ManagerRegistry $doctrine)
    {
        $this->ownerEntityClasses = $ownerEntityClasses;
        $this->doctrine = $doctrine;
    }

    /**
     * Removes all OAuth 2.0 clients associated with removed users.
     */
    public function cleanUp(): void
    {
        $em = $this->getEntityManager();
        foreach ($this->ownerEntityClasses as $ownerEntityClass) {
            $ownerSubQuery = $em->createQueryBuilder()
                ->from($ownerEntityClass, 'o')
                ->select('o.id')
                ->where('o.id = e.ownerEntityId')
                ->getQuery();
            $qb = $em->createQueryBuilder()
                ->delete(Client::class, 'e')
                ->where('e.ownerEntityClass = :ownerEntityClass')
                ->setParameter('ownerEntityClass', $ownerEntityClass);
            $qb->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists($ownerSubQuery->getDQL())
                )
            );
            $qb->getQuery()->execute();
        }
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(Client::class);
    }
}
