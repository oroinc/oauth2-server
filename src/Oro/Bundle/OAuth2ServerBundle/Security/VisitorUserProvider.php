<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Decorates a user provider and returns a customer visitor if a user name is a visitor identifier.
 * @see \Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Security\Factory\OAuth2Factory::getUserProvider
 */
class VisitorUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserProviderInterface $innerUserProvider,
        private CustomerVisitorManager $visitorManager
    ) {
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (VisitorIdentifierUtil::isVisitorIdentifier($identifier)) {
            return $this->visitorManager->findOrCreate(VisitorIdentifierUtil::decodeIdentifier($identifier));
        }

        return $this->innerUserProvider->loadUserByIdentifier($identifier);
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->innerUserProvider->refreshUser($user);
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return $this->innerUserProvider->supportsClass($class);
    }
}
