<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
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

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        if (VisitorIdentifierUtil::isVisitorIdentifier($username)) {
            list($visitorId, $visitorSessionId) = VisitorIdentifierUtil::decodeIdentifier($username);

            return $this->customerVisitorManager->find($visitorId, $visitorSessionId);
        }

        return $this->innerUserProvider->loadUserByUsername($username);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        return $this->innerUserProvider->refreshUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $this->innerUserProvider->supportsClass($class);
    }
}
