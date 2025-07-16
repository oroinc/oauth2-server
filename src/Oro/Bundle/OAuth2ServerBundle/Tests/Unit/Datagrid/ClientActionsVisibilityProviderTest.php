<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Datagrid;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\OAuth2ServerBundle\Datagrid\ClientActionsVisibilityProvider;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientActionsVisibilityProviderTest extends TestCase
{
    private ClientManager&MockObject $clientManager;
    private ClientActionsVisibilityProvider $visibilityProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->clientManager = $this->createMock(ClientManager::class);

        $this->visibilityProvider = new ClientActionsVisibilityProvider($this->clientManager);
    }

    public function testGetActionsVisibilityWhenClientModificationIsDenied(): void
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
            'update'     => [],
            'activate'   => [],
            'deactivate' => [],
            'delete'     => []
        ];

        $record->expects(self::once())
            ->method('getRootEntity')
            ->willReturn($client);
        $record->expects(self::once())
            ->method('getValue')
            ->with('active')
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isModificationGranted')
            ->with(self::identicalTo($client))
            ->willReturn(false);
        $this->clientManager->expects(self::once())
            ->method('isDeletionGranted')
            ->with(self::identicalTo($client))
            ->willReturn(true);

        self::assertEquals(
            [
                'update'     => false,
                'activate'   => false,
                'deactivate' => false,
                'delete'     => true
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }

    public function testGetActionsVisibilityForActiveClientAndClientModificationIsGranted(): void
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
            'update'     => [],
            'activate'   => [],
            'deactivate' => [],
            'delete'     => []
        ];

        $record->expects(self::once())
            ->method('getRootEntity')
            ->willReturn($client);
        $record->expects(self::once())
            ->method('getValue')
            ->with('active')
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isModificationGranted')
            ->with(self::identicalTo($client))
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isDeletionGranted')
            ->with(self::identicalTo($client))
            ->willReturn(true);

        self::assertEquals(
            [
                'update'     => true,
                'activate'   => false,
                'deactivate' => true,
                'delete'     => true
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }

    public function testGetActionsVisibilityForNotActiveClientAndClientModificationIsGranted(): void
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
            'update'     => [],
            'activate'   => [],
            'deactivate' => [],
            'delete'     => []
        ];

        $record->expects(self::once())
            ->method('getRootEntity')
            ->willReturn($client);
        $record->expects(self::once())
            ->method('getValue')
            ->with('active')
            ->willReturn(false);
        $this->clientManager->expects(self::once())
            ->method('isModificationGranted')
            ->with(self::identicalTo($client))
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isDeletionGranted')
            ->with(self::identicalTo($client))
            ->willReturn(true);

        self::assertEquals(
            [
                'update'     => true,
                'activate'   => true,
                'deactivate' => false,
                'delete'     => true
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }

    public function testGetActionsVisibilityForClientDeletionIsDenied(): void
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
            'update'     => [],
            'activate'   => [],
            'deactivate' => [],
            'delete'     => []
        ];

        $record->expects(self::once())
            ->method('getRootEntity')
            ->willReturn($client);
        $record->expects(self::once())
            ->method('getValue')
            ->with('active')
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isModificationGranted')
            ->with(self::identicalTo($client))
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isDeletionGranted')
            ->with(self::identicalTo($client))
            ->willReturn(false);

        self::assertEquals(
            [
                'update'     => true,
                'activate'   => false,
                'deactivate' => true,
                'delete'     => false
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }

    public function testGetActionsVisibilityWhenClientModificationAndDeletionAreDenied(): void
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
            'update'     => [],
            'activate'   => [],
            'deactivate' => [],
            'delete'     => []
        ];

        $record->expects(self::once())
            ->method('getRootEntity')
            ->willReturn($client);
        $record->expects(self::once())
            ->method('getValue')
            ->with('active')
            ->willReturn(true);
        $this->clientManager->expects(self::once())
            ->method('isModificationGranted')
            ->with(self::identicalTo($client))
            ->willReturn(false);
        $this->clientManager->expects(self::once())
            ->method('isDeletionGranted')
            ->with(self::identicalTo($client))
            ->willReturn(false);

        self::assertEquals(
            [
                'update'     => false,
                'activate'   => false,
                'deactivate' => false,
                'delete'     => false
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }
}
