<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\Token\AuthenticatedTokenTrait;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationAwareTokenInterface;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationAwareTokenTrait;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The token that represents OAuth 2.0 authentication data.
 */
class OAuth2Token extends AbstractToken implements OrganizationAwareTokenInterface
{
    use AuthenticatedTokenTrait;
    use OrganizationAwareTokenTrait;

    public const REQUEST_ATTRIBUTE      = 'request';
    public const PROVIDER_KEY_ATTRIBUTE = 'provider_key';

    /**
     * @param UserInterface $user
     * @param Organization  $organization
     */
    public function __construct(UserInterface $user = null, Organization $organization = null)
    {
        $this->setAuthenticated(false);

        if (null !== $user && null !== $organization) {
            $roles = $user->getRoles();

            parent::__construct($roles);

            $this->setUser($user);
            $this->setOrganization($organization);

            $this->setAuthenticated(\count($roles) > 0);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        if (!$this->hasIsGlobalMethod($this->organization)) {
            return parent::getRoles();
        }

        return $this->filterRolesByOrganization($this->organization, parent::getRoles());
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(): string
    {
        return '';
    }

    private function hasIsGlobalMethod(Organization $organization): bool
    {
        return method_exists($organization, 'getIsGlobal');
    }

    /**
     * @param Organization $organization
     * @param Role[]       $roles
     *
     * @return Role[]
     */
    private function filterRolesByOrganization(Organization $organization, array $roles): array
    {
        $organizationId = $organization->getId();
        foreach ($roles as $key => $role) {
            /** @var Organization $roleOrganization */
            $roleOrganization = $role->getOrganization();
            if ($roleOrganization && $roleOrganization->getId() !== $organizationId) {
                unset($roles[$key]);
            }
        }

        return $roles;
    }
}
