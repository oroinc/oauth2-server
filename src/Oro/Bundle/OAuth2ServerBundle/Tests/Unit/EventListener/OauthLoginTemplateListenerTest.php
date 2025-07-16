<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\EventListener\OauthLoginTemplateListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class OauthLoginTemplateListenerTest extends TestCase
{
    private ClientManager&MockObject $clientManager;
    private OauthLoginTemplateListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->listener = new OauthLoginTemplateListener($this->clientManager);
        $this->listener->addRoute('test_route');
    }

    public function testOnKernelViewOnNonSupportedRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'non_supported_route');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

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
            ->with('_security.main.target_path')
            ->willReturn(null);

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

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
            ->with('_security.main.target_path')
            ->willReturn('http://localhost');

        $event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );

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
        $request->attributes->set('_template', '@OroUserBundle/Some/template.html.twig');

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.main.target_path')
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

        $this->listener->onKernelView($event);

        self::assertEquals(['appName' => 'My App'], $event->getControllerResult());
        self::assertTrue($request->attributes->get('_oauth_login'));
        self::assertEquals(
            '@OroOAuth2Server/Some/template.html.twig',
            $request->attributes->get('_template')->getTemplate()
        );
    }
}
