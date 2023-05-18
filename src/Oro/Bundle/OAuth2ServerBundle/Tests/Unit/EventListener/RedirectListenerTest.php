<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\EventListener;

use Oro\Bundle\OAuth2ServerBundle\EventListener\RedirectListener;
use Oro\Bundle\PlatformBundle\EventListener\RedirectListenerInterface;
use Oro\Bundle\RedirectBundle\Routing\Router;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RedirectListenerTest extends TestCase
{
    private RedirectListenerInterface|MockObject $innerRedirectListener;

    private Router|MockObject $router;

    /** @var  */
    private RedirectListener $listener;

    protected function setUp(): void
    {
        $this->innerRedirectListener = $this->createMock(RedirectListenerInterface::class);
        $this->router = $this->createMock(Router::class);

        $this->listener = new RedirectListener(
            $this->innerRedirectListener,
            $this->router
        );
    }

    public function testOnRequest(): void
    {
        $request = Request::create('https://ua.orocommerce.com/oauth2-token');
        $response = new Response();
        $event = $this->getEvent($request, $response);

        $this->router->expects(self::once())
            ->method('matchRequest')
            ->with($event->getRequest())
            ->willReturn(['_route' => 'oro_oauth2_server_authenticate']);
        $this->innerRedirectListener->expects(self::never())
            ->method('onRequest');

        $this->listener->onRequest($event);
    }


    public function testOnRequestNoMatchesButOAuthTokenPath(): void
    {
        $request = Request::create('https://ua.orocommerce.com/oauth2-token/a-new-route');
        $response = new Response();
        $event = $this->getEvent($request, $response);

        $this->router->expects(self::once())
            ->method('matchRequest')
            ->with($event->getRequest())
            ->willReturn([]);
        $this->innerRedirectListener->expects(self::never())
            ->method('onRequest');

        $this->listener->onRequest($event);
    }

    public function testOnRequestRedirectNormally(): void
    {
        $request = Request::create('https://ua.orocommerce.com/product');
        $response = new Response();
        $event = $this->getEvent($request, $response);

        $this->innerRedirectListener->expects(self::once())
            ->method('onRequest')
            ->with(self::identicalTo($event));

        $this->listener->onRequest($event);
    }

    private function getEvent(Request $request, Response $response = null): RequestEvent
    {
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        if (null !== $response) {
            $event->setResponse($response);
        }

        return $event;
    }
}
