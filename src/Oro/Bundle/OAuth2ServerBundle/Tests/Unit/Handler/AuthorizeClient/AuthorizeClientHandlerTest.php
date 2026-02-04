<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandlerInterface;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use PHPUnit\Framework\TestCase;

class AuthorizeClientHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $clientEntity = $this->createMock(ClientEntity::class);
        $user = $this->createMock(UserInterface::class);
        $isAuthorized = true;

        $handler1 = $this->createMock(AuthorizeClientHandlerInterface::class);
        $handler1->expects(self::once())
            ->method('handle')
            ->with(self::identicalTo($clientEntity), self::identicalTo($user), $isAuthorized);

        $handler2 = $this->createMock(AuthorizeClientHandlerInterface::class);
        $handler2->expects(self::once())
            ->method('handle')
            ->with(self::identicalTo($clientEntity), self::identicalTo($user), $isAuthorized);

        $chainHandler = new AuthorizeClientHandler([$handler1, $handler2]);
        $chainHandler->handle($clientEntity, $user, $isAuthorized);
    }
}
