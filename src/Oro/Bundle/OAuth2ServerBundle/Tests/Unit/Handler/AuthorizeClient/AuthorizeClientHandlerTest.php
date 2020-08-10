<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandlerInterface;
use Oro\Bundle\UserBundle\Entity\User;

class AuthorizeClientHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testHandle()
    {
        $client = new Client();
        $user = new User();
        $isAuthorized = true;

        $handler1 = $this->createMock(AuthorizeClientHandlerInterface::class);
        $handler1->expects(self::once())
            ->method('handle')
            ->with($client, $user, $isAuthorized);

        $handler2 = $this->createMock(AuthorizeClientHandlerInterface::class);
        $handler2->expects(self::once())
            ->method('handle')
            ->with($client, $user, $isAuthorized);

        $chainHandler = new AuthorizeClientHandler([$handler1, $handler2]);
        $chainHandler->handle($client, $user, $isAuthorized);
    }
}
