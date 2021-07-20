<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;

/**
 * The listener that adds "grants" column to OAuth 2.0 clients grid query
 * if the "show_grants" parameter is set to TRUE.
 */
class GrantClientDatagridListener
{
    public function onBuildBefore(BuildBefore $event): void
    {
        $parameters = $event->getDatagrid()->getParameters();
        if ($parameters->get('show_grants')) {
            $event->getConfig()->getOrmQuery()
                ->addSelect('client.grants');
        }
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $parameters = $datagrid->getParameters();
        if ($parameters->get('show_grants')) {
            $this->addGrantColumn($datagrid->getConfig());
        }
    }

    private function addGrantColumn(DatagridConfiguration $config): void
    {
        $config->offsetSetByPath(
            '[columns]',
            array_merge(
                $config->offsetGetByPath('[columns]'),
                [
                    'grants' => [
                        'label'         => 'oro.oauth2server.client.grants.label',
                        'type'          => 'twig',
                        'frontend_type' => 'html',
                        'template'      => '@OroOAuth2Server/Client/Datagrid/grants.html.twig',
                        'translatable'  => true,
                        'editable'      => false
                    ]
                ]
            )
        );
    }
}
