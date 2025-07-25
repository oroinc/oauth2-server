<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent;
use Oro\Bundle\OAuth2ServerBundle\EventListener\NavigationListener;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NavigationListenerTest extends TestCase
{
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private TokenAccessorInterface&MockObject $tokenAccessor;
    private ApiFeatureChecker&MockObject $featureChecker;
    private NavigationListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->featureChecker = $this->createMock(ApiFeatureChecker::class);

        $this->listener = new NavigationListener(
            $this->authorizationChecker,
            $this->tokenAccessor,
            $this->featureChecker
        );
    }

    public function testOnNavigationConfigureWithoutSystemTab(): void
    {
        $menu = new MenuItem('test', new MenuFactory());
        $event = new ConfigureMenuEvent($this->createMock(FactoryInterface::class), $menu);

        $this->listener->onNavigationConfigure($event);

        $this->assertNull($menu->getChild('frontend_oauth_applications'));
    }

    public function testOnNavigationConfigureWithSystemTab(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->tokenAccessor->expects($this->once())
            ->method('hasUser')
            ->willReturn(true);

        $this->featureChecker->expects($this->once())
            ->method('isFrontendApiEnabled')
            ->willReturn(true);

        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('oro_oauth2_view')
            ->willReturn(true);

        $menu = new MenuItem('test', new MenuFactory());
        $menu->addChild('customers_tab');
        $event = new ConfigureMenuEvent($this->createMock(FactoryInterface::class), $menu);

        $this->listener->onNavigationConfigure($event);

        $menuItem = $menu->getChild('customers_tab')->getChild('frontend_oauth_applications');
        $this->assertEquals('oro.oauth2server.menu.frontend_oauth_application.label', $menuItem->getLabel());
    }

    public function testOnNavigationConfigureWithSystemTabWhenUserIsNotGrantedToSeeOauthClients(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->tokenAccessor->expects($this->once())
            ->method('hasUser')
            ->willReturn(true);

        $this->featureChecker->expects($this->once())
            ->method('isFrontendApiEnabled')
            ->willReturn(true);

        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('oro_oauth2_view')
            ->willReturn(false);

        $menu = new MenuItem('test', new MenuFactory());
        $menu->addChild('customers_tab');
        $event = new ConfigureMenuEvent($this->createMock(FactoryInterface::class), $menu);

        $this->listener->onNavigationConfigure($event);

        $this->assertNull($menu->getChild('customers_tab')->getChild('frontend_oauth_applications'));
    }

    public function testOnNavigationConfigureWithSystemTabWothoutUserInToken(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->tokenAccessor->expects($this->once())
            ->method('hasUser')
            ->willReturn(false);
        $this->featureChecker->expects($this->once())
            ->method('isFrontendApiEnabled')
            ->willReturn(true);

        $menu = new MenuItem('test', new MenuFactory());
        $menu->addChild('customers_tab');
        $event = new ConfigureMenuEvent($this->createMock(FactoryInterface::class), $menu);

        $this->listener->onNavigationConfigure($event);

        $this->assertNull($menu->getChild('customers_tab')->getChild('frontend_oauth_applications'));
    }

    public function testOnNavigationConfigureWithoutCustomerPortal(): void
    {
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only without CustomerBundle');
        }

        $menu = new MenuItem('test', new MenuFactory());
        $menu->addChild('system_tab', []);
        $event = new ConfigureMenuEvent($this->createMock(FactoryInterface::class), $menu);

        $this->featureChecker->expects($this->never())
            ->method('isFrontendApiEnabled');

        $this->listener->onNavigationConfigure($event);

        $this->assertNull($menu->getChild('system_tab')->getChild('frontend_oauth_applications'));
    }
}
