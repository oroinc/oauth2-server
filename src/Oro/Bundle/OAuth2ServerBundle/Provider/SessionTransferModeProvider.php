<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Checks whether the current session was created via Session Transfer.
 */
class SessionTransferModeProvider
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function isInSessionTransferMode(): bool
    {
        $token = $this->tokenStorage->getToken();

        return $token instanceof SessionTransferAuthenticationToken
            || $token instanceof SessionTransferCustomerVisitorAuthenticationToken;
    }
}
