<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception\AuthCodeGrantExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class AuthCodeGrantExceptionHandlerTest extends TestCase
{
    private AuthCodeLogAttemptHelper&MockObject $logAttemptHelper;
    private AuthCodeGrantExceptionHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->logAttemptHelper = $this->createMock(AuthCodeLogAttemptHelper::class);

        $this->handler = new AuthCodeGrantExceptionHandler($this->logAttemptHelper);
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
            ->with($request, 'OAuth', $exception);

        $this->handler->handle($request, $exception);
    }
}
