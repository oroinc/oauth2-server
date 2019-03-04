<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
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
        $isModificationGranted = $this->clientManager->isModificationGranted($record->getRootEntity());
        $isActive = (bool)$record->getValue('active');

        $visibility = [];
        foreach ($actions as $action => $actionConfig) {
            $visible = $isModificationGranted;
            if ($visible) {
                if ('activate' === $action) {
                    $visible = !$isActive;
                } elseif ('deactivate' === $action) {
                    $visible = $isActive;
                }
            }
            $visibility[$action] = $visible;
        }

        return $visibility;
    }
}
