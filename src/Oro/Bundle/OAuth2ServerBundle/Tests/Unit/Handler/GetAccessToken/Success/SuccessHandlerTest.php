<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\SuccessHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\SuccessHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class SuccessHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testHandle()
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $handler1 = $this->createMock(SuccessHandlerInterface::class);
        $handler1->expects(self::once())
            ->method('handle')
            ->with($request);

        $handler2 = $this->createMock(SuccessHandlerInterface::class);
        $handler2->expects(self::once())
            ->method('handle')
            ->with($request);

        $chainHandler = new SuccessHandler([$handler1, $handler2]);
        $chainHandler->handle($request);
    }
}
