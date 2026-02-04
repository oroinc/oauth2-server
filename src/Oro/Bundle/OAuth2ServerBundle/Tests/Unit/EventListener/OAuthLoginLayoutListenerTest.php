<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\LayoutBundle\Attribute\Layout;
use Oro\Bundle\LayoutBundle\EventListener\LayoutListener;
use Oro\Bundle\OAuth2ServerBundle\EventListener\OAuthLoginLayoutListener;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ExtendedClientRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class OAuthLoginLayoutListenerTest extends TestCase
{
    private LayoutListener&MockObject $layoutListener;
    private ExtendedClientRepositoryInterface&MockObject $clientRepository;
    private ServerRequestFactoryInterface&MockObject $serverRequestFactory;
    private OAuthLoginLayoutListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->layoutListener = $this->createMock(LayoutListener::class);
        $this->clientRepository = $this->createMock(ExtendedClientRepositoryInterface::class);
        $this->serverRequestFactory = $this->createMock(ServerRequestFactoryInterface::class);

        $this->listener = new OAuthLoginLayoutListener(
            $this->layoutListener,
            $this->clientRepository,
            $this->serverRequestFactory
        );
        $this->listener->addRoute('test_route');
        $this->listener->addRoute('test1_route');
    }

    private function getEvent(Request $request): ViewEvent
    {
        return new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            []
        );
    }

    public function testOnKernelViewWithNonSupportedRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'non_supported_route');

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
    }

    public function testOnKernelViewWithoutSession(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

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

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

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

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
    }

    public function testOnKernelView(): void
    {
        $client = new ClientEntity();
        $client->setName('My App');
        $request = new Request();
        $request->attributes->set('_route', 'test_route');
        $targetRequestUri = 'http://localhost?client_id=123567';
        $targetRequestQueryParams = ['client_id' => '123567'];

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn($targetRequestUri);

        $targetRequest = $this->createMock(ServerRequestInterface::class);
        $targetRequest->expects(self::once())
            ->method('withQueryParams')
            ->with($targetRequestQueryParams)
            ->willReturnSelf();
        $targetRequest->expects(self::once())
            ->method('getQueryParams')
            ->willReturn($targetRequestQueryParams);
        $this->serverRequestFactory->expects(self::once())
            ->method('createServerRequest')
            ->with('GET', $targetRequestUri)
            ->willReturn($targetRequest);

        $this->clientRepository->expects(self::once())
            ->method('isSpecialClientIdentifier')
            ->with('123567')
            ->willReturn(false);
        $this->clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with('123567')
            ->willReturn($client);

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals(
            ['data' => ['appName' => 'My App'], 'route_name' => 'oauth_test_route'],
            $event->getControllerResult()
        );
        self::assertTrue($request->attributes->get('_oauth_login'));
    }

    public function testOnKernelViewForSpecialClientId(): void
    {
        $client = new ClientEntity();
        $client->setName('My App');
        $request = new Request();
        $request->attributes->set('_route', 'test_route');
        $targetRequestUri = 'http://localhost?client_id=123567';
        $targetRequestQueryParams = ['client_id' => '123567'];

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn($targetRequestUri);

        $targetRequest = $this->createMock(ServerRequestInterface::class);
        $targetRequest->expects(self::once())
            ->method('withQueryParams')
            ->with($targetRequestQueryParams)
            ->willReturnSelf();
        $targetRequest->expects(self::once())
            ->method('getQueryParams')
            ->willReturn($targetRequestQueryParams);
        $this->serverRequestFactory->expects(self::once())
            ->method('createServerRequest')
            ->with('GET', $targetRequestUri)
            ->willReturn($targetRequest);

        $this->clientRepository->expects(self::once())
            ->method('isSpecialClientIdentifier')
            ->with('123567')
            ->willReturn(true);
        $this->clientRepository->expects(self::once())
            ->method('findClientEntity')
            ->with('123567', self::identicalTo($targetRequest))
            ->willReturn($client);

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals(
            ['data' => ['appName' => 'My App'], 'route_name' => 'oauth_test_route'],
            $event->getControllerResult()
        );
        self::assertTrue($request->attributes->get('_oauth_login'));
    }

    public function testOnKernelViewWhenClientNotFound(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');
        $targetRequestUri = 'http://localhost?client_id=123567';
        $targetRequestQueryParams = ['client_id' => '123567'];

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn($targetRequestUri);

        $targetRequest = $this->createMock(ServerRequestInterface::class);
        $targetRequest->expects(self::once())
            ->method('withQueryParams')
            ->with($targetRequestQueryParams)
            ->willReturnSelf();
        $targetRequest->expects(self::once())
            ->method('getQueryParams')
            ->willReturn($targetRequestQueryParams);
        $this->serverRequestFactory->expects(self::once())
            ->method('createServerRequest')
            ->with('GET', $targetRequestUri)
            ->willReturn($targetRequest);

        $this->clientRepository->expects(self::once())
            ->method('isSpecialClientIdentifier')
            ->with('123567')
            ->willReturn(true);
        $this->clientRepository->expects(self::once())
            ->method('findClientEntity')
            ->with('123567', self::identicalTo($targetRequest))
            ->willReturn(null);

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals(
            ['data' => ['appName' => null], 'route_name' => 'oauth_test_route'],
            $event->getControllerResult()
        );
        self::assertTrue($request->attributes->get('_oauth_login'));
    }

    public function testOnKernelViewWhenClientNotFoundDueToInvalidRequest(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test_route');
        $targetRequestUri = 'http://localhost?client_id=123567';
        $targetRequestQueryParams = ['client_id' => '123567'];

        $session = $this->createMock(Session::class);
        $request->setSession($session);

        $session->expects(self::once())
            ->method('get')
            ->with('_security.frontend.target_path')
            ->willReturn($targetRequestUri);

        $targetRequest = $this->createMock(ServerRequestInterface::class);
        $targetRequest->expects(self::once())
            ->method('withQueryParams')
            ->with($targetRequestQueryParams)
            ->willReturnSelf();
        $targetRequest->expects(self::once())
            ->method('getQueryParams')
            ->willReturn($targetRequestQueryParams);
        $this->serverRequestFactory->expects(self::once())
            ->method('createServerRequest')
            ->with('GET', $targetRequestUri)
            ->willReturn($targetRequest);

        $this->clientRepository->expects(self::once())
            ->method('isSpecialClientIdentifier')
            ->with('123567')
            ->willReturn(true);
        $this->clientRepository->expects(self::once())
            ->method('findClientEntity')
            ->with('123567', self::identicalTo($targetRequest))
            ->willThrowException(OAuthServerException::serverError('some error'));

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals(
            ['data' => ['appName' => null], 'route_name' => 'oauth_test_route'],
            $event->getControllerResult()
        );
        self::assertTrue($request->attributes->get('_oauth_login'));
    }

    public function testOnKernelViewWithAuthRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'oro_oauth2_server_frontend_authenticate');

        $event = $this->getEvent($request);
        $this->layoutListener->expects(self::once())
            ->method('onKernelView')
            ->with(self::identicalTo($event));

        $this->listener->onKernelView($event);

        self::assertEquals([], $event->getControllerResult());
        self::assertFalse($request->attributes->has('_oauth_login'));
        self::assertInstanceOf(Layout::class, $request->attributes->get('_layout'));
    }
}
