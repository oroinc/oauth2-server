<?php

namespace Oro\Bundle\OAuth2ServerBundle\Entity\Cleaner;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

/**
 * The cleaner that removes OAuth 2.0 clients that belong to removed users.
 */
class ClientCleaner
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
     * Removes all OAuth 2.0 clients associated with removed users.
     */
    public function cleanUp(): void
    {
        $em = $this->getEntityManager();
        $ownerEntityClasses = $this->getClientOwnerEntityClasses();
        foreach ($ownerEntityClasses as $ownerEntityClass) {
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

    /**
     * @return string[]
     */
    private function getClientOwnerEntityClasses(): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->from(Client::class, 'e')
            ->select('e.ownerEntityClass')
            ->distinct();
        $rows = $qb->getQuery()->getArrayResult();

        return array_map(
            function (array $row) {
                return $row['ownerEntityClass'];
            },
            $rows
        );
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(Client::class);
    }
}
