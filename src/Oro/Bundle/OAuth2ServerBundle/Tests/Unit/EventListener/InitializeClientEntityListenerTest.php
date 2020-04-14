<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\FormBundle\Event\FormHandler\FormProcessEvent;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\EventListener\InitializeClientEntityListener;
use Symfony\Component\Form\FormInterface;

class InitializeClientEntityListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var InitializeClientEntityListener */
    private $listener;

    protected function setUp(): void
    {
        $this->clientManager = $this->createMock(ClientManager::class);

        $this->listener = new InitializeClientEntityListener($this->clientManager);
    }

    public function testUpdateClient()
    {
        $client = $this->createMock(Client::class);

        $this->clientManager->expects(self::once())
            ->method('updateClient')
            ->with(self::identicalTo($client), self::isFalse());

        $this->listener->updateClient(new FormProcessEvent($this->createMock(FormInterface::class), $client));
    }
}
