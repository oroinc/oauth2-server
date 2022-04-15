<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\LogAuthorizeClientHandler;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use Oro\Component\Testing\Unit\EntityTrait;

class LogAuthorizeClientHandlerTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    public function testHandleOnAuthorize()
    {
        $client = $this->getEntity(Client::class, ['id' => 123, 'identifier' => 'test_identifier']);
        $user = new User();

        $userLoginAttemptLogger = $this->createMock(UserLoginAttemptLogger::class);
        $userLoginAttemptLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with(
                $user,
                'OAuthCode',
                [
                    'OAuthApp' => [
                        'id'         => 123,
                        'identifier' => 'test_identifier',
                    ]
                ]
            );

        $handler = new LogAuthorizeClientHandler($userLoginAttemptLogger);
        $handler->handle($client, $user, true);
    }

    public function testHandleOnNotAuthorize()
    {
        $client = $this->getEntity(Client::class, ['id' => 123, 'identifier' => 'test_identifier']);
        $user = new User();

        $userLoginAttemptLogger = $this->createMock(UserLoginAttemptLogger::class);
        $userLoginAttemptLogger->expects(self::once())
            ->method('logFailedLoginAttempt')
            ->with(
                $user,
                'OAuthCode',
                [
                    'OAuthApp' => [
                        'id'         => 123,
                        'identifier' => 'test_identifier',
                    ]
                ]
            );

        $handler = new LogAuthorizeClientHandler($userLoginAttemptLogger);
        $handler->handle($client, $user, false);
    }
}
