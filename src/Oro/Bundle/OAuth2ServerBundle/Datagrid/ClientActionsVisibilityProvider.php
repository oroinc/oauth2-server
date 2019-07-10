<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;

/**
 * Configures actions' visibility for OAuth 2.0 clients datagrid.
 */
class ClientActionsVisibilityProvider
{
    /** @var ClientManager */
    private $clientManager;

    /**
     * @param ClientManager $clientManager
     */
    public function __construct($clientManager)
    {
        $this->clientManager = $clientManager;
    }

    /**
     * @param ResultRecordInterface $record
     * @param array                 $actions
     *
     * @return array
     */
    public function getActionsVisibility(ResultRecordInterface $record, array $actions)
    {
        /** @var Client $client */
        $client = $record->getRootEntity();
        $isModificationGranted = $this->clientManager->isModificationGranted($client);
        $isDeletionGranted = $this->clientManager->isDeletionGranted($client);
        $isActive = (bool)$record->getValue('active');

        $visibility = [];
        if (isset($actions['update'])) {
            $visibility['update'] = $isModificationGranted;
        }
        if (isset($actions['activate'])) {
            $visibility['activate'] = $isModificationGranted && !$isActive;
        }
        if (isset($actions['deactivate'])) {
            $visibility['deactivate'] = $isModificationGranted && $isActive;
        }
        if (isset($actions['delete'])) {
            $visibility['delete'] = $isDeletionGranted;
        }

        return $visibility;
    }
}
