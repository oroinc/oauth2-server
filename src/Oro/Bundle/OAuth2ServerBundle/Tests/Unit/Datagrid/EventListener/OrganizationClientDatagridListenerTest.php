<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Datagrid\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener\OrganizationClientDatagridListener;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

class OrganizationClientDatagridListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var TokenAccessorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenAccessor;

    /** @var OrganizationClientDatagridListener */
    private $listener;

    protected function setUp()
    {
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);

        $this->listener = new OrganizationClientDatagridListener($this->tokenAccessor);
    }

    public function testOnBuildBeforeWhenNoOrganizationInSecurityContext()
    {
        $where = [
            'and' => [
                'client.ownerEntityClass = :ownerEntityClass'
            ]
        ];
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'where' => $where
                ]
            ]
        ]);

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn(null);

        $this->listener->onBuildBefore(new BuildBefore($this->createMock(DatagridInterface::class), $config));

        self::assertEquals(
            $where,
            $config->getOrmQuery()->getWhere()
        );
    }

    public function testOnBuildBeforeWhenSecurityContextHasOrganization()
    {
        $where = [
            'and' => [
                'client.ownerEntityClass = :ownerEntityClass'
            ]
        ];
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'where' => $where
                ]
            ]
        ]);

        $organizationId = 123;
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::once())
            ->method('getId')
            ->willReturn($organizationId);

        $this->tokenAccessor->expects(self::once())
            ->method('getOrganization')
            ->willReturn($organization);

        $this->listener->onBuildBefore(new BuildBefore($this->createMock(DatagridInterface::class), $config));

        $expectedWhere = $where;
        $expectedWhere['and'][] = 'IDENTITY(client.organization) = ' . $organizationId;
        self::assertEquals(
            $expectedWhere,
            $config->getOrmQuery()->getWhere()
        );
    }
}
