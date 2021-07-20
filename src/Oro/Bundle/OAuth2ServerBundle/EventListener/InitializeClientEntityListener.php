<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\FormBundle\Event\FormHandler\FormProcessEvent;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;

/**
 * The listener that sets missing, auto-generated and default values to OAuth 2.0 client entity
 * before it is stored into the database.
 */
class InitializeClientEntityListener
{
    /** @var ClientManager */
    private $clientManager;

    public function __construct(ClientManager $clientManager)
    {
        $this->clientManager = $clientManager;
    }

    public function updateClient(FormProcessEvent $event): void
    {
        $this->clientManager->updateClient($event->getData(), false);
    }
}
