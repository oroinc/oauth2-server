<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\LogAuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Psr\Http\Message\ServerRequestInterface;

class LogAuthorizeClientHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var AuthCodeLogAttemptHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $logAttemptHelper;

    /** @var LogAuthorizeClientHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->logAttemptHelper = $this->createMock(AuthCodeLogAttemptHelper::class);
        $this->handler = new LogAuthorizeClientHandler($this->logAttemptHelper);
    }

    public function testHandleWithNonAuthCodeRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = OAuthServerException::accessDenied();

        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'password']);

        $this->logAttemptHelper->expects(self::never())
            ->method('logFailedLoginAttempt');

        $this->handler->handle($request, $exception);
    }

    public function testHandleWithAuthCodeRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = OAuthServerException::accessDenied();

        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'authorization_code']);

        $this->logAttemptHelper->expects(self::once())
            ->method('logFailedLoginAttempt')
            ->with($request, 'OAuthCode', $exception);

        $this->handler->handle($request, $exception);
    }
}
