<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception\LogExceptionHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class LogExceptionHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testHandle()
    {
        $exception = OAuthServerException::accessDenied();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with($exception->getMessage(), ['exception' => $exception]);

        $handler = new LogExceptionHandler($logger);
        $handler->handle($this->createMock(ServerRequestInterface::class), $exception);
    }
}
