<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmQueryConfiguration;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\EventListener\PasswordApplicationsGridListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PasswordApplicationsGridListenerTest extends TestCase
{
    private FeatureChecker&MockObject $featureChecker;
    private PasswordApplicationsGridListener $passwordApplicationsGridListener;

    protected function setUp(): void
    {
        $this->featureChecker = $this->createMock(FeatureChecker::class);
        $this->passwordApplicationsGridListener = new PasswordApplicationsGridListener($this->featureChecker);
    }

    public function testOnBuildBeforeOnFrontentGrid(): void
    {
        $parameters = new ParameterBag(['frontend' => true]);
        $datagrid = $this->createMock(DatagridInterface::class);
        $config = $this->createMock(DatagridConfiguration::class);
        $event = new BuildBefore($datagrid, $config);

        $datagrid->expects(self::once())
            ->method('getParameters')
            ->willReturn($parameters);

        $this->featureChecker->expects(self::never())
            ->method('isFeatureEnabled');

        $config->expects(self::never())
            ->method('getOrmQuery');

        $this->passwordApplicationsGridListener->onBuildBefore($event);
    }

    public function testOnBuildBeforeOnEnabledFeature(): void
    {
        $parameters = new ParameterBag(['frontend' => false]);
        $datagrid = $this->createMock(DatagridInterface::class);
        $config = $this->createMock(DatagridConfiguration::class);
        $event = new BuildBefore($datagrid, $config);

        $datagrid->expects(self::once())
            ->method('getParameters')
            ->willReturn($parameters);

        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('user_login_password')
            ->willReturn(true);

        $config->expects(self::never())
            ->method('getOrmQuery');

        $this->passwordApplicationsGridListener->onBuildBefore($event);
    }

    public function testOnBuildBeforeOnDisabledFeature(): void
    {
        $parameters = new ParameterBag(['frontend' => false]);
        $datagrid = $this->createMock(DatagridInterface::class);
        $config = $this->createMock(DatagridConfiguration::class);
        $query = $this->createMock(OrmQueryConfiguration::class);
        $event = new BuildBefore($datagrid, $config);

        $datagrid->expects(self::once())
            ->method('getParameters')
            ->willReturn($parameters);

        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('user_login_password')
            ->willReturn(false);

        $config->expects(self::once())
            ->method('getOrmQuery')
            ->willReturn($query);

        $query->expects(self::once())
            ->method('addAndWhere')
            ->with('client.grants != \'password\'');

        $this->passwordApplicationsGridListener->onBuildBefore($event);
    }
}
