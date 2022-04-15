<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\ExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\ExceptionHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExceptionHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testHandle(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = OAuthServerException::accessDenied();

        $handler1 = $this->createMock(ExceptionHandlerInterface::class);
        $handler1->expects(self::once())
            ->method('handle')
            ->with($request, $exception);

        $handler2 = $this->createMock(ExceptionHandlerInterface::class);
        $handler2->expects(self::once())
            ->method('handle')
            ->with($request, $exception);

        $chainHandler = new ExceptionHandler([$handler1, $handler2]);
        $chainHandler->handle($request, $exception);
    }
}
