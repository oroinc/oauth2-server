<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * The listener that adds client owner column.
 */
class OwnerClientDatagridListener
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function addOwnerColumn(BuildAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $isFrontend = (bool)$datagrid->getParameters()->get('frontend');
        $config = $datagrid->getConfig();
        $config->offsetSetByPath(
            '[columns]',
            array_merge(
                $config->offsetGetByPath('[columns]'),
                [
                    'owner' => [
                        'label'         => $isFrontend
                            ? 'oro.customer.customeruser.entity_label'
                            : 'oro.user.entity_label',
                        'type'          => 'twig',
                        'frontend_type' => 'html',
                        'template'      => '@OroOAuth2Server/Client/Datagrid/owners.html.twig',
                        'translatable'  => true,
                        'editable'      => false,
                        'renderable'    => true
                    ]
                ]
            )
        );
    }

    public function addOwnerData(OrmResultAfter $event): void
    {
        $ownerIds = [];
        foreach ($event->getRecords() as $record) {
            $ownerIds[] = $record->getValue('ownerEntityId');
        }

        $ownerIds = array_unique($ownerIds);
        if (count($ownerIds) === 0) {
            return;
        }

        $isFrontend = (bool)$event->getDatagrid()->getParameters()->get('frontend');
        $ownerClass = $isFrontend ? CustomerUser::class : User::class;
        $owners = $this->getOwners($ownerClass, array_unique($ownerIds));
        if (count($owners) === 0) {
            return;
        }

        foreach ($event->getRecords() as $record) {
            $ownerId = $record->getValue('ownerEntityId');
            if (!$ownerId || !array_key_exists($ownerId, $owners)) {
                continue;
            }

            $record->setValue('owner', $owners[$ownerId]);
        }
    }

    private function getOwners(string $ownerClass, array $ownerIds): array
    {
        $qb = $this->doctrine->getManagerForClass($ownerClass)->createQueryBuilder()
            ->select('o')
            ->from($ownerClass, 'o')
            ->where('o.id in (:ids)')
            ->setParameter('ids', $ownerIds);

        $owners = $qb->getQuery()->getResult();
        $result = [];

        foreach ($owners as $owner) {
            $result[$owner->getId()] = $owner;
        }

        return $result;
    }
}
