<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Provider;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Oro\Bundle\CustomerBundle\Entity\CustomerUserRole;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\AbstractUser;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The authentication provider that returns AnonymousCustomerUserToken
 * if an user object is not an instance of AbstractUser class.
 */
class VisitorOAuth2Provider extends OAuth2Provider
{
    /** @var WebsiteManager */
    private $websiteManager;

    public function __construct(
        UserProviderInterface $userProvider,
        string $providerKey,
        AuthorizationValidatorInterface $authorizationValidator,
        ManagerRegistry $doctrine,
        UserCheckerInterface $userChecker,
        WebsiteManager $websiteManager
    ) {
        parent::__construct(
            $userProvider,
            $providerKey,
            $authorizationValidator,
            $doctrine,
            $userChecker
        );
        $this->websiteManager = $websiteManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateUserAndGetToken($user, Organization $organization): TokenInterface
    {
        if ($user instanceof AbstractUser) {
            return parent::validateUserAndGetToken($user, $organization);
        }

        return new AnonymousCustomerUserToken(
            'Anonymous Customer User',
            $this->getVisitorRoles(),
            $user,
            $organization
        );
    }

    private function getVisitorRoles(): array
    {
        $currentWebsite = $this->websiteManager->getCurrentWebsite();
        if (null === $currentWebsite) {
            return [];
        }

        /** @var CustomerUserRole $guestRole */
        $guestRole = $currentWebsite->getGuestRole();

        return [$guestRole->getRole()];
    }
}
