<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception\LogExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\ExtendedOAuthServerException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LogExceptionHandlerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private LogExceptionHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new LogExceptionHandler($this->logger);
    }

    public function testHandleSimpleException(): void
    {
        $exception = OAuthServerException::accessDenied();

        $this->logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                $exception->getMessage(),
                [
                    'exception' => $exception,
                    'errorType' => 'access_denied'
                ]
            );

        $this->handler->handle($this->createMock(ServerRequestInterface::class), $exception);
    }

    public function testHandleExceptionWithHint(): void
    {
        $exception = OAuthServerException::accessDenied('OAuth application was not found.');

        $this->logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                $exception->getMessage(),
                [
                    'exception' => $exception,
                    'errorType' => 'access_denied',
                    'hint' => 'OAuth application was not found.'
                ]
            );

        $this->handler->handle($this->createMock(ServerRequestInterface::class), $exception);
    }

    public function testHandleExceptionWithAdditionalLogInfo(): void
    {
        $exception = ExtendedOAuthServerException::accessDenied('Found several OAuth applications.')
            ->withLogLevel(LogLevel::WARNING)
            ->withLogContext(['oauthApplications' => ['App 1', 'App 2']]);

        $this->logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                $exception->getMessage(),
                [
                    'exception' => $exception,
                    'errorType' => 'access_denied',
                    'hint' => 'Found several OAuth applications.',
                    'oauthApplications' => ['App 1', 'App 2']
                ]
            );

        $this->handler->handle($this->createMock(ServerRequestInterface::class), $exception);
    }
}
