<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
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

    #[\Override]
    public function handle(ClientEntity $clientEntity, UserInterface $user, bool $isAuthorized): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($clientEntity, $user, $isAuthorized);
        }
    }
}
