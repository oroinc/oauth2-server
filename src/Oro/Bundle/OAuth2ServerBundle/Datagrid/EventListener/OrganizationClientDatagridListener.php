<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;

/**
 * The listener that adds filter by organization to OAuth 2.0 clients grid query
 * and adds organization column if multi organization is supported.
 */
class OrganizationClientDatagridListener
{
    /** @var ClientOwnerOrganizationsProvider */
    private $organizationsProvider;

    public function __construct(ClientOwnerOrganizationsProvider $organizationsProvider)
    {
        $this->organizationsProvider = $organizationsProvider;
    }

    public function onBuildBefore(BuildBefore $event): void
    {
        if (!$this->organizationsProvider->isMultiOrganizationSupported()) {
            return;
        }

        $event->getConfig()->getOrmQuery()
            ->addSelect('org.name AS organizationName')
            ->addInnerJoin('client.organization', 'org')
            ->addAndWhere('org.id IN (:organizationIds)');
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        if (!$this->organizationsProvider->isMultiOrganizationSupported()) {
            return;
        }

        $datagrid = $event->getDatagrid();
        $parameters = $datagrid->getParameters();
        $organizations = $this->organizationsProvider->getClientOwnerOrganizations(
            $parameters->get('ownerEntityClass'),
            $parameters->get('ownerEntityId')
        );

        $parameters->set(
            'organizationIds',
            array_map(
                function (Organization $organization) {
                    return $organization->getId();
                },
                $organizations
            )
        );
        $datagrid->getDatasource()->bindParameters(['organizationIds']);

        if ($this->organizationsProvider->isOrganizationSelectorRequired($organizations)) {
            $this->addOrganizationColumn($datagrid->getConfig(), $organizations);
        }
    }

    /**
     * @param DatagridConfiguration $config
     * @param Organization[]        $organizations
     */
    private function addOrganizationColumn(DatagridConfiguration $config, array $organizations): void
    {
        $config->offsetSetByPath(
            '[columns]',
            array_merge(
                [
                    'organization' => [
                        'label'         => 'oro.organization.entity_label',
                        'type'          => 'field',
                        'frontend_type' => 'string',
                        'translatable'  => true,
                        'editable'      => false,
                        'renderable'    => true
                    ]
                ],
                $config->offsetGetByPath('[columns]')
            )
        );

        $config->offsetSetByPath(
            '[sorters][columns]',
            array_merge(
                [
                    'organization' => [
                        'data_name' => 'org.name'
                    ]
                ],
                $config->offsetGetByPath('[sorters][columns]')
            )
        );
        $config->offsetSetByPath('[sorters][default]', ['organization' => 'ASC']);

        $config->offsetSetByPath(
            '[filters][columns]',
            array_merge(
                [
                    'organization' => [
                        'type'         => 'choice',
                        'data_name'    => 'org.id',
                        'renderable'   => true,
                        'translatable' => true,
                        'options'      => [
                            'field_options' => [
                                'choices'              => $this->getOrganizationChoices($organizations),
                                'multiple'             => true,
                                'translatable_options' => false
                            ]
                        ]
                    ]
                ],
                $config->offsetGetByPath('[filters][columns]')
            )
        );
    }

    /**
     * @param Organization[] $organizations
     *
     * @return array
     */
    private function getOrganizationChoices(array $organizations): array
    {
        $result = [];
        $organizations = $this->organizationsProvider->sortOrganizations($organizations);
        foreach ($organizations as $organization) {
            $result[$organization->getName()] = $organization->getId();
        }

        return $result;
    }
}
