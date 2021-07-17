<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Entity\UserInterface;

/**
 * Delegates handling of OAuth application authorization.
 */
class AuthorizeClientHandler implements AuthorizeClientHandlerInterface
{
    /** @var AuthorizeClientHandlerInterface[] */
    private $handlers;

    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Client $client, UserInterface $user, bool $isAuthorized): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($client, $user, $isAuthorized);
        }
    }
}
