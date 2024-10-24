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
    /** @var UserProviderInterface */
    private $innerUserProvider;

    /** @var CustomerVisitorManager */
    private $customerVisitorManager;

    public function __construct(
        UserProviderInterface $innerUserProvider,
        CustomerVisitorManager $customerVisitorManager
    ) {
        $this->innerUserProvider = $innerUserProvider;
        $this->customerVisitorManager = $customerVisitorManager;
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (VisitorIdentifierUtil::isVisitorIdentifier($identifier)) {
            list($visitorId, $visitorSessionId) = VisitorIdentifierUtil::decodeIdentifier($identifier);

            return $this->customerVisitorManager->find($visitorId, $visitorSessionId);
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
