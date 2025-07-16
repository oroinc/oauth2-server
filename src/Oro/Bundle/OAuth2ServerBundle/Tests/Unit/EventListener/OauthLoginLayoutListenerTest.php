<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\LayoutBundle\Attribute\Layout;
use Oro\Bundle\LayoutBundle\EventListener\LayoutListener;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\EventListener\OauthLoginLayoutListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class OauthLoginLayoutListenerTest extends TestCase
{
    private ClientManager&MockObject $clientManager;
    private LayoutListener&MockObject $layoutListener;
    private OauthLoginLayoutListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->layoutListener = $this->createMock(LayoutListener::class);

        $this->listener = new OauthLoginLayoutListener($this->clientManager, $this->layoutListener);
        $this->listener->addRoute('test_route');
        $this->listener->addRoute('test1_route');
    }

    public function testOnKernelViewWithNonSupportedRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'non_supported_route');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with($event);

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
    }

    public function testOnKernelViewWithoutSession(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with($event);

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
    }

    public function testOnKernelViewWithoutTargetPathParameterInSession(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn(null);

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with($event);

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
    }

    public function testOnKernelViewWithoutClientIdParameterInTargetPath(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn('http://localhost');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with($event);

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
    }

    public function testOnKernelView(): void
    {
        $client = new Client();
        $client->setName('My App');
        $request = new Request();
        $request->attributes->set('_route', 'test_route');

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn('http://localhost?client_id=123567');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('123567')
            ->willReturn($client);

        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with($event);

        $this->listener->onKernelView($event);

        self::assertEquals(
            ['data' => ['appName' => 'My App'], 'route_name' => 'oauth_test_route'],
            $event->getControllerResult()
        );
        self::assertTrue($request->attributes->get('_oauth_login'));
    }

    public function testOnKernelViewWithAuthRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'oro_oauth2_server_frontend_authenticate');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with($event);

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
        self::assertInstanceOf(Layout::class, $request->attributes->get('_layout'));
    }
}
