<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Datagrid;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\OAuth2ServerBundle\Datagrid\ClientActionsVisibilityProvider;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;

class ClientActionsVisibilityProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var ClientActionsVisibilityProvider */
    private $visibilityProvider;

    protected function setUp()
    {
        $this->clientManager = $this->createMock(ClientManager::class);

        $this->visibilityProvider = new ClientActionsVisibilityProvider($this->clientManager);
    }

    public function testGetActionsVisibilityWhenClientModificationIsDenied()
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
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

        self::assertEquals(
            [
                'activate'   => false,
                'deactivate' => false,
                'delete'     => false
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }

    public function testGetActionsVisibilityForActiveClientAndClientModificationIsGranted()
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
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

        self::assertEquals(
            [
                'activate'   => false,
                'deactivate' => true,
                'delete'     => true
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }

    public function testGetActionsVisibilityForNotActiveClientAndClientModificationIsGranted()
    {
        $client = $this->createMock(Client::class);
        $record = $this->createMock(ResultRecordInterface::class);
        $actions = [
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

        self::assertEquals(
            [
                'activate'   => true,
                'deactivate' => false,
                'delete'     => true
            ],
            $this->visibilityProvider->getActionsVisibility($record, $actions)
        );
    }
}
