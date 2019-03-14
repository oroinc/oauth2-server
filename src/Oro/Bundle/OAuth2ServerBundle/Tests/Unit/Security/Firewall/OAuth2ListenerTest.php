<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authentication\Firewall;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Firewall\OAuth2Listener;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Zend\Diactoros\ServerRequest;

class OAuth2ListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var TokenStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenStorage;

    /** @var AuthenticationManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $authenticationManager;

    /** @var HttpMessageFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $httpMessageFactory;

    /** @var OAuth2Listener */
    private $listener;

    protected function setUp()
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->authenticationManager = $this->createMock(AuthenticationManagerInterface::class);
        $this->httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);

        $this->listener = new OAuth2Listener(
            $this->tokenStorage,
            $this->authenticationManager,
            $this->httpMessageFactory,
            'test_firewall'
        );
    }

    public function testHandleWithRequestWithoutAuthorizationHeader()
    {
        $request = new Request();
        $event = new GetResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::class
        );

        $this->httpMessageFactory->expects($this->once())
            ->method('createRequest')
            ->with($request)
            ->willReturn(new ServerRequest());
        $this->authenticationManager->expects($this->never())
            ->method('authenticate');
        $this->tokenStorage->expects($this->never())
            ->method('setToken');

        $this->listener->handle($event);
    }

    public function testHandleWithRequestWithNotSupportedAuthorizationHeader()
    {
        $headers = ['Authorization' => 'Basic YWxhZGRpbjpvcGVuc2VzYW1l'];
        $serverRequest = new ServerRequest([], [], 'http://test.com/test_api', 'POST', 'php://input', $headers);

        $request = new Request();
        $event = new GetResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::class
        );

        $this->httpMessageFactory->expects($this->once())
            ->method('createRequest')
            ->with($request)
            ->willReturn($serverRequest);
        $this->authenticationManager->expects($this->never())
            ->method('authenticate');
        $this->tokenStorage->expects($this->never())
            ->method('setToken');

        $this->listener->handle($event);
    }

    public function testHandleWithCorrectAuthorizationHeader()
    {
        $headers = ['Authorization' => 'Bearer YWxhZGRpbjpvcGVuc2VzYW1l'];
        $serverRequest = new ServerRequest([], [], 'http://test.com/test_api', 'POST', 'php://input', $headers);

        $request = new Request();
        $event = new GetResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::class
        );

        $expectedToken = new OAuth2Token();
        $expectedToken->setAttribute(OAuth2Token::REQUEST_ATTRIBUTE, $serverRequest);
        $expectedToken->setAttribute(OAuth2Token::PROVIDER_KEY_ATTRIBUTE, 'test_firewall');

        $this->httpMessageFactory->expects($this->once())
            ->method('createRequest')
            ->with($request)
            ->willReturn($serverRequest);
        $this->authenticationManager->expects($this->once())
            ->method('authenticate')
            ->with($expectedToken)
            ->willReturn($expectedToken);
        $this->tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($expectedToken);

        $this->listener->handle($event);
    }
}
