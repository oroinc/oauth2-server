<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Datagrid\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener\GrantClientDatagridListener;

class GrantClientDatagridListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var GrantClientDatagridListener */
    private $listener;

    protected function setUp(): void
    {
        $this->listener = new GrantClientDatagridListener();
    }

    public function testOnBuildBeforeWhenShowGrantsIsNotRequested()
    {
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'select' => [
                        'client.id'
                    ]
                ]
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $datagrid = new Datagrid('test', $config, $parameters);

        $this->listener->onBuildBefore(new BuildBefore($datagrid, $config));

        self::assertSame($initialConfig, $config->toArray());
    }

    public function testOnBuildBeforeWhenShowGrantsIsRequested()
    {
        $config = DatagridConfiguration::create([
            'source' => [
                'type'  => 'orm',
                'query' => [
                    'select' => [
                        'client.id'
                    ]
                ]
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $parameters->set('show_grants', true);
        $datagrid = new Datagrid('test', $config, $parameters);

        $this->listener->onBuildBefore(new BuildBefore($datagrid, $config));

        $expectedConfig = $initialConfig;
        $expectedConfig['source']['query']['select'][] = 'client.grants';
        self::assertSame($expectedConfig, $config->toArray());
    }

    public function testOnBuildAfterWhenShowGrantsIsNotRequested()
    {
        $config = DatagridConfiguration::create([
            'columns' => [
                'name' => []
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $datagrid = new Datagrid('test', $config, $parameters);

        $this->listener->onBuildAfter(new BuildAfter($datagrid));

        self::assertSame($initialConfig, $config->toArray());
    }

    public function testOnBuildAfterWhenShowGrantsIsRequested()
    {
        $config = DatagridConfiguration::create([
            'columns' => [
                'name' => []
            ]
        ]);
        $initialConfig = $config->toArray();
        $parameters = new ParameterBag();
        $parameters->set('show_grants', true);
        $datagrid = new Datagrid('test', $config, $parameters);

        $this->listener->onBuildAfter(new BuildAfter($datagrid));

        $expectedConfig = $initialConfig;
        $expectedConfig['columns']['grants'] = [
            'label'         => 'oro.oauth2server.client.grants.label',
            'type'          => 'twig',
            'frontend_type' => 'html',
            'template'      => '@OroOAuth2Server/Client/Datagrid/grants.html.twig',
            'translatable'  => true,
            'editable'      => false
        ];
        self::assertSame($expectedConfig, $config->toArray());
    }
}
