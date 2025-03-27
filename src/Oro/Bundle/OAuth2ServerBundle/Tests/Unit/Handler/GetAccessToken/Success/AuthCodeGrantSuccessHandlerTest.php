<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use Defuse\Crypto\Crypto;
use GuzzleHttp\Psr7\ServerRequest;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\AuthCodeGrantSuccessHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\InteractiveLoginEventDispatcher;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthCodeGrantSuccessHandlerTest extends TestCase
{
    private AuthCodeLogAttemptHelper&MockObject $logAttemptHelper;
    private RequestStack&MockObject $requestStack;
    private InteractiveLoginEventDispatcher&MockObject $interactiveLoginEventDispatcher;
    private AuthCodeGrantSuccessHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->logAttemptHelper = $this->createMock(AuthCodeLogAttemptHelper::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->interactiveLoginEventDispatcher = $this->createMock(InteractiveLoginEventDispatcher::class);

        $this->handler = new AuthCodeGrantSuccessHandler(
            $this->logAttemptHelper,
            $this->requestStack,
            $this->interactiveLoginEventDispatcher
        );
        $this->handler->setEncryptionKey('test_encryption_key');
    }

    public function testHandleForNonAuthCodeRequest(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'other']);

        $this->requestStack->expects(self::never())
            ->method('getMainRequest');
        $this->logAttemptHelper->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $this->interactiveLoginEventDispatcher->expects(self::never())
            ->method('dispatch');

        $this->handler->handle($request);
    }

    public function testHandle(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => 'test_client',
                'code' => Crypto::encryptWithPassword(json_encode(
                    ['user_id' => 'test_user'],
                    JSON_THROW_ON_ERROR
                ), 'test_encryption_key')
            ]);

        $symfonyRequest = $this->createMock(Request::class);

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->logAttemptHelper->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($request, 'OAuth');
        $this->interactiveLoginEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::identicalTo($symfonyRequest), 'test_client', 'test_user');

        $this->handler->handle($request);
    }

    public function testHandleWhenNoMainRequest(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => 'test_client',
                'code' => Crypto::encryptWithPassword(json_encode(
                    ['user_id' => 'test_user'],
                    JSON_THROW_ON_ERROR
                ), 'test_encryption_key')
            ]);

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn(null);
        $this->logAttemptHelper->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($request, 'OAuth');
        $this->interactiveLoginEventDispatcher->expects(self::never())
            ->method('dispatch');

        $this->handler->handle($request);
    }

    public function testHandleWhenUserIdentifierContainsVisitorSessionId(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => 'test_client',
                'code' => Crypto::encryptWithPassword(json_encode(
                    ['user_id' => 'test_user|visitor:test_visitor_session_id'],
                    JSON_THROW_ON_ERROR
                ), 'test_encryption_key')
            ]);

        $symfonyRequest = Request::create('/');

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->interactiveLoginEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::identicalTo($symfonyRequest), 'test_client', 'test_user')
            ->willReturnCallback(function (Request $symfonyRequest) {
                self::assertEquals('test_visitor_session_id', $symfonyRequest->attributes->get('visitor_session_id'));
            });

        $this->handler->handle($request);
    }

    public function testHandleWhenUserIdentifierContainsEmptyVisitorSessionId(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => 'test_client',
                'code' => Crypto::encryptWithPassword(json_encode(
                    ['user_id' => 'test_user|visitor:'],
                    JSON_THROW_ON_ERROR
                ), 'test_encryption_key')
            ]);

        $symfonyRequest = Request::create('/');

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->interactiveLoginEventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::identicalTo($symfonyRequest), 'test_client', 'test_user')
            ->willReturnCallback(function (Request $symfonyRequest) {
                self::assertFalse($symfonyRequest->attributes->has('visitor_session_id'));
            });

        $this->handler->handle($request);
    }
}
