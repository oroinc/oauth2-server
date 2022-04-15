<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\LogAuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\AuthCodeGrantSuccessHandler;
use Oro\Bundle\OAuth2ServerBundle\Provider\AuthCodeLogAttemptHelper;
use Psr\Http\Message\ServerRequestInterface;

class AuthCodeGrantSuccessHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var AuthCodeLogAttemptHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $logAttemptHelper;

    /** @var LogAuthorizeClientHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->logAttemptHelper = $this->createMock(AuthCodeLogAttemptHelper::class);
        $this->handler = new AuthCodeGrantSuccessHandler($this->logAttemptHelper);
    }

    public function testHandleWithNonAuthCodeRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'password']);

        $this->logAttemptHelper->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $this->handler->handle($request);
    }

    public function testHandleWithAuthCodeRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'authorization_code']);

        $this->logAttemptHelper->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($request, 'OAuth');

        $this->handler->handle($request);
    }
}
