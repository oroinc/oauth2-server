<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Datagrid\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter as QueryParameter;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Datasource\Orm\ParameterBinder;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener\OrganizationClientDatagridListener;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;

class OrganizationClientDatagridListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ClientOwnerOrganizationsProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $organizationsProvider;

    /** @var OrganizationClientDatagridListener */
    private $listener;

    protected function setUp(): void
    {
        $this->organizationsProvider = $this->createMock(ClientOwnerOrganizationsProvider::class);

        $this->listener = new OrganizationClientDatagridListener(
            $this->organizationsProvider
        );
    }

    private function getOrganization(int $id, string $name): Organization
    {
        $organization = $this->createMock(Organization::class);
        $organization->expects(self::any())
            ->method('getId')
            ->willReturn($id);
        $organization->expects(self::any())
            ->method('getName')
            ->willReturn($name);

        return $organization;
    }

    private function setDatasource(Datagrid $datagrid, ArrayCollection $queryParameters)
    {
        $datasource = $this->createMock(OrmDatasource::class);
        $qb = $this->createMock(QueryBuilder::class);

        $datagrid->setDatasource($datasource);

        $datasource->expects(self::once())
            ->method('bindParameters')
            ->willReturnCallback(function ($datasourceToDatagridParameters, $append) use ($datagrid) {
                $binder = new ParameterBinder();
                $binder->bindParameters($datagrid, $datasourceToDatagridParameters, $append);
            });
        $datasource->expects(self::any())
            ->method('getQueryBuilder')
            ->willReturn($qb);
        $qb->expects(self::any())
            ->method('getParameters')
            ->willReturn($queryParameters);
    }

    public function testOnBuildBeforeWhenMultiOrganizationIsNotSupported()
    {
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'where' => [
                        'and' => [
                            'client.ownerEntityClass = :ownerEntityClass'
                        ]
                    ]
                ]
            ]
        ]);
        $initialConfig = $config->toArray();

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(false);

        $this->listener->onBuildBefore(new BuildBefore($this->createMock(DatagridInterface::class), $config));

        self::assertSame($initialConfig, $config->toArray());
    }

    public function testOnBuildBeforeWhenMultiOrganizationIsSupported()
    {
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'select' => [
                        'client.id'
                    ],
                    'where'  => [
                        'and' => [
                            'client.ownerEntityClass = :ownerEntityClass'
                        ]
                    ]
                ]
            ]
        ]);
        $initialConfig = $config->toArray();

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(true);

        $this->listener->onBuildBefore(new BuildBefore($this->createMock(DatagridInterface::class), $config));

        $expectedConfig = $initialConfig;
        $expectedConfig['source']['query']['select'][] = 'org.name AS organizationName';
        $expectedConfig['source']['query']['join'] = [
            'inner' => [['join' => 'client.organization', 'alias' => 'org']]
        ];
        $expectedConfig['source']['query']['where']['and'][] = 'org.id IN (:organizationIds)';
        self::assertSame($expectedConfig, $config->toArray());
    }

    public function testOnBuildAfterWhenMultiOrganizationIsNotSupported()
    {
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'where' => [
                        'and' => [
                            'client.ownerEntityClass = :ownerEntityClass',
                            'org.id IN (:organizationIds)'
                        ]
                    ]
                ]
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $parameters->set('ownerEntityClass', 'Test\OwnerEntity');
        $parameters->set('ownerEntityId', 123);
        $datasource = $this->createMock(OrmDatasource::class);
        $datagrid = new Datagrid('test', $config, $parameters);
        $datagrid->setDatasource($datasource);

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(false);
        $datasource->expects(self::never())
            ->method('bindParameters');

        $this->listener->onBuildAfter(new BuildAfter($datagrid));

        self::assertSame($initialConfig, $config->toArray());
    }

    public function testOnBuildAfterWhenMultiOrganizationIsSupportedAndClientOwnerBelongsToOneOrganization()
    {
        $config = DatagridConfiguration::create([
            'source'  => [
                'type'  => 'orm',
                'query' => [
                    'where' => [
                        'and' => [
                            'client.ownerEntityClass = :ownerEntityClass',
                            'org.id IN (:organizationIds)'
                        ]
                    ]
                ]
            ],
            'columns' => [
                'name' => []
            ],
            'sorters' => [
                'columns' => [
                    'name' => []
                ]
            ],
            'filters' => [
                'columns' => [
                    'name' => []
                ]
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $parameters->set('ownerEntityClass', 'Test\OwnerEntity');
        $parameters->set('ownerEntityId', 123);

        $datagrid = new Datagrid('test', $config, $parameters);
        $queryParameters = $this->getMockBuilder(ArrayCollection::class)
            ->onlyMethods(['clear', 'add'])
            ->getMock();
        $this->setDatasource($datagrid, $queryParameters);

        $organization1 = $this->getOrganization(10, 'org1');

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(true);
        $this->organizationsProvider->expects(self::once())
            ->method('getClientOwnerOrganizations')
            ->with($parameters->get('ownerEntityClass'), $parameters->get('ownerEntityId'))
            ->willReturn([$organization1]);
        $this->organizationsProvider->expects(self::once())
            ->method('isOrganizationSelectorRequired')
            ->with([$organization1])
            ->willReturn(false);

        $queryParameters->expects(self::never())
            ->method('clear');
        $queryParameters->expects(self::once())
            ->method('add')
            ->with(new QueryParameter('organizationIds', [$organization1->getId()]));

        $this->listener->onBuildAfter(new BuildAfter($datagrid));

        self::assertSame($initialConfig, $config->toArray());
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testOnBuildAfterWhenMultiOrganizationIsSupportedAndClientOwnerBelongsToSeveralOrganizations()
    {
        $config = DatagridConfiguration::create([
            'source'  => [
                'type'  => 'orm',
                'query' => [
                    'where' => [
                        'and' => [
                            'client.ownerEntityClass = :ownerEntityClass',
                            'org.id IN (:organizationIds)'
                        ]
                    ]
                ]
            ],
            'columns' => [
                'name' => []
            ],
            'sorters' => [
                'columns' => [
                    'name' => []
                ]
            ],
            'filters' => [
                'columns' => [
                    'name' => []
                ]
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $parameters->set('ownerEntityClass', 'Test\OwnerEntity');
        $parameters->set('ownerEntityId', 123);

        $datagrid = new Datagrid('test', $config, $parameters);
        $queryParameters = $this->getMockBuilder(ArrayCollection::class)
            ->onlyMethods(['clear', 'add'])
            ->getMock();
        $this->setDatasource($datagrid, $queryParameters);

        $organization1 = $this->getOrganization(10, 'org1');
        $organization2 = $this->getOrganization(20, 'org2');

        $this->organizationsProvider->expects(self::once())
            ->method('isMultiOrganizationSupported')
            ->willReturn(true);
        $this->organizationsProvider->expects(self::once())
            ->method('getClientOwnerOrganizations')
            ->with($parameters->get('ownerEntityClass'), $parameters->get('ownerEntityId'))
            ->willReturn([$organization1, $organization2]);
        $this->organizationsProvider->expects(self::once())
            ->method('isOrganizationSelectorRequired')
            ->with([$organization1, $organization2])
            ->willReturn(true);
        $this->organizationsProvider->expects(self::once())
            ->method('sortOrganizations')
            ->with([$organization1, $organization2])
            ->willReturn([$organization2, $organization1]);

        $queryParameters->expects(self::never())
            ->method('clear');
        $queryParameters->expects(self::once())
            ->method('add')
            ->with(new QueryParameter('organizationIds', [$organization1->getId(), $organization2->getId()]));

        $this->listener->onBuildAfter(new BuildAfter($datagrid));

        $expectedConfig = $initialConfig;
        $expectedConfig['columns'] = array_merge(
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
            $expectedConfig['columns']
        );
        $expectedConfig['sorters']['columns'] = array_merge(
            [
                'organization' => [
                    'data_name' => 'org.name'
                ]
            ],
            $expectedConfig['sorters']['columns']
        );
        $expectedConfig['sorters']['default'] = ['organization' => 'ASC'];
        $expectedConfig['filters']['columns'] = array_merge(
            [
                'organization' => [
                    'type'         => 'choice',
                    'data_name'    => 'org.id',
                    'renderable'   => true,
                    'translatable' => true,
                    'options'      => [
                        'field_options' => [
                            'choices'              => [
                                $organization2->getName() => $organization2->getId(),
                                $organization1->getName() => $organization1->getId()
                            ],
                            'multiple'             => true,
                            'translatable_options' => false
                        ]
                    ]
                ]
            ],
            $expectedConfig['filters']['columns']
        );
        self::assertSame($expectedConfig, $config->toArray());
    }
}
