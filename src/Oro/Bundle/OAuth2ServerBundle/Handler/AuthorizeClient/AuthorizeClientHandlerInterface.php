<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\UserBundle\Entity\UserInterface;

/**
 * Represents a handler of OAuth application authorization.
 */
interface AuthorizeClientHandlerInterface
{
    public function handle(ClientEntity $clientEntity, UserInterface $user, bool $isAuthorized): void;
}
