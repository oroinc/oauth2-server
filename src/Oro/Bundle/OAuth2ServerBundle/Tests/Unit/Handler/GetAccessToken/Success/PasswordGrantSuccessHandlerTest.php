<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use GuzzleHttp\Psr7\ServerRequest;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\InteractiveLoginEventDispatcher;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\PasswordGrantSuccessHandler;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorAccessTokenParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PasswordGrantSuccessHandlerTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private InteractiveLoginEventDispatcher&MockObject $interactiveLoginEventDispatcher;
    private VisitorAccessTokenParser&MockObject $visitorAccessTokenParser;
    private PasswordGrantSuccessHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->interactiveLoginEventDispatcher = $this->createMock(InteractiveLoginEventDispatcher::class);
        $this->visitorAccessTokenParser = $this->createMock(VisitorAccessTokenParser::class);

        $this->handler = new PasswordGrantSuccessHandler(
            $this->requestStack,
            $this->interactiveLoginEventDispatcher,
            $this->visitorAccessTokenParser
        );
    }

    public function testHandleForNonPasswordGrant(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'other']);

        $this->requestStack->expects(self::never())
            ->method('getMainRequest');
        $this->visitorAccessTokenParser->expects(self::never())
            ->method('getVisitorSessionId');
        $this->interactiveLoginEventDispatcher->expects(self::never())
            ->method('dispatch');

        $this->handler->handle($request);
    }

    public function testHandle(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'password',
                'client_id' => 'test_client',
                'username' => 'test_user'
            ]);

        $symfonyRequest = $this->createMock(Request::class);

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->visitorAccessTokenParser->expects(self::never())
            ->method('getVisitorSessionId');
        $this->interactiveLoginEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::identicalTo($symfonyRequest), 'test_client', 'test_user');

        $this->handler->handle($request);
    }

    public function testHandleWhenNoMainRequest(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'password',
                'client_id' => 'test_client',
                'username' => 'test_user'
            ]);

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn(null);
        $this->visitorAccessTokenParser->expects(self::never())
            ->method('getVisitorSessionId');
        $this->interactiveLoginEventDispatcher->expects(self::never())
            ->method('dispatch');

        $this->handler->handle($request);
    }

    public function testHandleWithVisitorAccessToken(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'password',
                'client_id' => 'test_client',
                'username' => 'test_user',
                'visitor_access_token' => 'test_visitor_access_token'
            ]);

        $symfonyRequest = Request::create('/');

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->visitorAccessTokenParser->expects(self::once())
            ->method('getVisitorSessionId')
            ->with('test_visitor_access_token')
            ->willReturn('test_visitor_session_id');
        $this->interactiveLoginEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::identicalTo($symfonyRequest), 'test_client', 'test_user')
            ->willReturnCallback(function (Request $symfonyRequest) {
                self::assertEquals('test_visitor_session_id', $symfonyRequest->attributes->get('visitor_session_id'));
            });

        $this->handler->handle($request);
    }

    public function testHandleWithInvalidVisitorAccessToken(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'password',
                'client_id' => 'test_client',
                'username' => 'test_user',
                'visitor_access_token' => 'test_visitor_access_token'
            ]);

        $symfonyRequest = Request::create('/');

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->visitorAccessTokenParser->expects(self::once())
            ->method('getVisitorSessionId')
            ->with('test_visitor_access_token')
            ->willReturn(null);
        $this->interactiveLoginEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::identicalTo($symfonyRequest), 'test_client', 'test_user')
            ->willReturnCallback(function (Request $symfonyRequest) {
                self::assertFalse($symfonyRequest->attributes->has('visitor_session_id'));
            });

        $this->handler->handle($request);
    }
}
