<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Firewall;

use GuzzleHttp\Psr7\ServerRequest;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Firewall\OAuth2Listener;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

    protected function setUp(): void
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

    public function testHandleWithRequestWithoutAuthorizationHeader(): void
    {
        $request = new Request();
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $this->httpMessageFactory->expects(self::never())
            ->method('createRequest')
            ->with($request);
        $this->authenticationManager->expects(self::never())
            ->method('authenticate');
        $this->tokenStorage->expects(self::never())
            ->method('setToken');

        ($this->listener)($event);
    }

    public function testHandleWithRequestWithNotSupportedAuthorizationHeader(): void
    {
        $headers = ['Authorization' => 'Basic YWxhZGRpbjpvcGVuc2VzYW1l'];

        $request = Request::create('http://test.com/test_api', 'POST');
        $request->headers->add($headers);
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $this->httpMessageFactory->expects(self::never())
            ->method('createRequest')
            ->with($request);
        $this->authenticationManager->expects(self::never())
            ->method('authenticate');
        $this->tokenStorage->expects(self::never())
            ->method('setToken');

        ($this->listener)($event);
    }

    /**
     * @dataProvider bearerAuthorizationHeaderDataProvider
     */
    public function testHandleWithCorrectAuthorizationHeader(string $bearerAuthorizationHeader): void
    {
        $headers = ['Authorization' => $bearerAuthorizationHeader];
        $serverRequest = (new ServerRequest('POST', 'http://test.com/test_api', $headers));

        $request = Request::create($serverRequest->getUri(), $serverRequest->getMethod());
        $request->headers->add($headers);
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $expectedToken = new OAuth2Token();
        $expectedToken->setAttribute(OAuth2Token::REQUEST_ATTRIBUTE, $serverRequest);
        $expectedToken->setAttribute(OAuth2Token::PROVIDER_KEY_ATTRIBUTE, 'test_firewall');

        $this->httpMessageFactory->expects(self::once())
            ->method('createRequest')
            ->with($request)
            ->willReturn($serverRequest);
        $this->authenticationManager->expects(self::once())
            ->method('authenticate')
            ->with($expectedToken)
            ->willReturn($expectedToken);
        $this->tokenStorage->expects(self::once())
            ->method('setToken')
            ->with($expectedToken);

        ($this->listener)($event);
    }

    public function bearerAuthorizationHeaderDataProvider(): array
    {
        return [
            ['Bearer YWxhZGRpbjpvcGVuc2VzYW1l'],
            [' Bearer YWxhZGRpbjpvcGVuc2VzYW1l'],
        ];
    }
}
