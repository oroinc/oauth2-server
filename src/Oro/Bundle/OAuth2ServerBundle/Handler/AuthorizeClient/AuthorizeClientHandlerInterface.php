<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Entity\UserInterface;

/**
 * Represents a handler of OAuth application authorization.
 */
interface AuthorizeClientHandlerInterface
{
    public function handle(Client $client, UserInterface $user, bool $isAuthorized): void;
}
