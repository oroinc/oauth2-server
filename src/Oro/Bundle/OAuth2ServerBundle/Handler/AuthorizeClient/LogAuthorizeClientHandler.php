<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Provider\UserLoggingInfoProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * The handler that logs the OAuth application authorization result.
 */
class LogAuthorizeClientHandler implements AuthorizeClientHandlerInterface
{
    private const APPLICATION_AUTHORIZED = 'OAuth application authorized';
    private const APPLICATION_NOT_AUTHORIZED = 'OAuth application not authorized';

    private LoggerInterface $logger;
    private UserLoggingInfoProviderInterface $loggingInfoProvider;

    public function __construct(LoggerInterface $logger, UserLoggingInfoProviderInterface $loggingInfoProvider)
    {
        $this->logger = $logger;
        $this->loggingInfoProvider = $loggingInfoProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Client $client, UserInterface $user, bool $isAuthorized): void
    {
        $this->logger->notice(
            $isAuthorized ? self::APPLICATION_AUTHORIZED : self::APPLICATION_NOT_AUTHORIZED,
            array_merge(
                $this->loggingInfoProvider->getUserLoggingInfo($user),
                [
                    'OAuthApp' => [
                        'id'         => $client->getId(),
                        'identifier' => $client->getIdentifier()
                    ]
                ]
            )
        );
    }
}
