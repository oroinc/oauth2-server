<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\LogAuthorizeClientHandler;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Provider\UserLoggingInfoProvider;
use Oro\Component\Testing\Unit\EntityTrait;
use Psr\Log\LoggerInterface;

class LogAuthorizeClientHandlerTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    public function testHandleOnAuthorize()
    {
        $client = $this->getEntity(Client::class, ['id' => 123, 'identifier' => 'test_identifier']);
        $user = new User();

        $infoProvider = $this->createMock(UserLoggingInfoProvider::class);
        $infoProvider->expects(self::once())
            ->method('getUserLoggingInfo')
            ->with($user)
            ->willReturn(['id' => 14]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('notice')
            ->with(
                'OAuth application authorized',
                [
                    'id'       => 14,
                    'OAuthApp' => [
                        'id'         => 123,
                        'identifier' => 'test_identifier',
                    ],
                ]
            );

        $handler = new LogAuthorizeClientHandler($logger, $infoProvider);
        $handler->handle($client, $user, true);
    }

    public function testHandleOnNotAuthorize()
    {
        $client = $this->getEntity(Client::class, ['id' => 123, 'identifier' => 'test_identifier']);
        $user = new User();

        $infoProvider = $this->createMock(UserLoggingInfoProvider::class);
        $infoProvider->expects(self::once())
            ->method('getUserLoggingInfo')
            ->with($user)
            ->willReturn(['id' => 14]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('notice')
            ->with(
                'OAuth application not authorized',
                [
                    'id'       => 14,
                    'OAuthApp' => [
                        'id'         => 123,
                        'identifier' => 'test_identifier',
                    ],
                ]
            );

        $handler = new LogAuthorizeClientHandler($logger, $infoProvider);
        $handler->handle($client, $user, false);
    }
}
