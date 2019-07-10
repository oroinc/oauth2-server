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
    /**
     * @param BuildBefore $event
     */
    public function onBuildBefore(BuildBefore $event): void
    {
        $parameters = $event->getDatagrid()->getParameters();
        if ($parameters->get('show_grants')) {
            $event->getConfig()->getOrmQuery()
                ->addSelect('client.grants');
        }
    }

    /**
     * @param BuildAfter $event
     */
    public function onBuildAfter(BuildAfter $event): void
    {
        $datagrid = $event->getDatagrid();
        $parameters = $datagrid->getParameters();
        if ($parameters->get('show_grants')) {
            $this->addGrantColumn($datagrid->getConfig());
        }
    }

    /**
     * @param DatagridConfiguration $config
     */
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
                        'template'      => 'OroOAuth2ServerBundle:Client:Datagrid/grants.html.twig',
                        'translatable'  => true,
                        'editable'      => false
                    ]
                ]
            )
        );
    }
}
