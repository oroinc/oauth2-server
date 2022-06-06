<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Provider;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Oro\Bundle\CustomerBundle\Security\AnonymousCustomerUserRolesProvider;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\AbstractUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The authentication provider that returns AnonymousCustomerUserToken
 * if an user object is not an instance of AbstractUser class.
 */
class VisitorOAuth2Provider extends OAuth2Provider
{
    private AnonymousCustomerUserRolesProvider $rolesProvider;

    public function __construct(
        UserProviderInterface $userProvider,
        string $providerKey,
        AuthorizationValidatorInterface $authorizationValidator,
        ManagerRegistry $doctrine,
        UserCheckerInterface $userChecker,
        ClientManager $clientManager,
        AnonymousCustomerUserRolesProvider $rolesProvider
    ) {
        parent::__construct(
            $userProvider,
            $providerKey,
            $authorizationValidator,
            $doctrine,
            $userChecker,
            $clientManager
        );
        $this->rolesProvider = $rolesProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateUserAndGetToken(object $user, Organization $organization): TokenInterface
    {
        if ($user instanceof AbstractUser) {
            return parent::validateUserAndGetToken($user, $organization);
        }

        return new AnonymousCustomerUserToken(
            'Anonymous Customer User',
            $this->rolesProvider->getRoles(),
            $user,
            $organization
        );
    }
}
