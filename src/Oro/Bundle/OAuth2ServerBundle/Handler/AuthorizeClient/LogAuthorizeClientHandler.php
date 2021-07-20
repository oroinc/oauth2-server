<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Provider\UserLoggingInfoProvider;
use Psr\Log\LoggerInterface;

/**
 * The handler that logs the OAuth application authorization result.
 */
class LogAuthorizeClientHandler implements AuthorizeClientHandlerInterface
{
    private const APPLICATION_AUTHORIZED     = 'OAuth application authorized';
    private const APPLICATION_NOT_AUTHORIZED = 'OAuth application not authorized';

    /** @var LoggerInterface */
    private $logger;

    /** @var UserLoggingInfoProvider */
    private $infoProvider;

    public function __construct(LoggerInterface $logger, UserLoggingInfoProvider $infoProvider)
    {
        $this->logger = $logger;
        $this->infoProvider = $infoProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Client $client, UserInterface $user, bool $isAuthorized): void
    {
        $this->logger->notice(
            $isAuthorized ? self::APPLICATION_AUTHORIZED : self::APPLICATION_NOT_AUTHORIZED,
            array_merge(
                $this->infoProvider->getUserLoggingInfo($user),
                [
                    'OAuthApp' => [
                        'id'         => $client->getId(),
                        'identifier' => $client->getIdentifier(),
                    ],
                ]
            )
        );
    }
}
