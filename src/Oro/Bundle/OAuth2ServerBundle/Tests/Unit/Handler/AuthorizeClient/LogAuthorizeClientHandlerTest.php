<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\LogAuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LogAuthorizeClientHandlerTest extends TestCase
{
    private UserLoginAttemptLogger&MockObject $userLoginAttemptLogger;
    private LogAuthorizeClientHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->userLoginAttemptLogger = $this->createMock(UserLoginAttemptLogger::class);

        $this->handler = new LogAuthorizeClientHandler($this->userLoginAttemptLogger);
    }

    public function testHandleOnAuthorize(): void
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('test_identifier');
        $user = $this->createMock(UserInterface::class);

        $this->userLoginAttemptLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with(
                self::identicalTo($user),
                'OAuthCode',
                ['OAuthApp' => ['identifier' => 'test_identifier']]
            );

        $this->handler->handle($clientEntity, $user, true);
    }

    public function testHandleOnNotAuthorize(): void
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier('test_identifier');
        $user = $this->createMock(UserInterface::class);

        $this->userLoginAttemptLogger->expects(self::once())
            ->method('logFailedLoginAttempt')
            ->with(
                self::identicalTo($user),
                'OAuthCode',
                ['OAuthApp' => ['identifier' => 'test_identifier']]
            );

        $this->handler->handle($clientEntity, $user, false);
    }
}
