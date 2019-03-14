<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationContextTokenInterface;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationContextTokenSerializerTrait;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The token that represents OAuth 2.0 authentication data.
 */
class OAuth2Token extends AbstractToken implements OrganizationContextTokenInterface
{
    public const REQUEST_ATTRIBUTE      = 'request';
    public const PROVIDER_KEY_ATTRIBUTE = 'provider_key';

    use OrganizationContextTokenSerializerTrait;

    /**
     * @param UserInterface $user
     * @param Organization  $organization
     */
    public function __construct(UserInterface $user = null, Organization $organization = null)
    {
        $this->setAuthenticated(false);

        if ($user && $organization) {
            $roles = $user->getRoles();

            parent::__construct($roles);

            $this->setUser($user);
            $this->setOrganizationContext($organization);

            $this->setAuthenticated(\count($roles) > 0);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        if (!$this->hasIsGlobalMethod($this->organizationContext)) {
            return parent::getRoles();
        }

        return $this->filterRolesInOrganizationContext();
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(): string
    {
        return '';
    }

    /**
     * @param Organization $organization
     *
     * @return bool
     */
    private function hasIsGlobalMethod(Organization $organization): bool
    {
        return method_exists($organization, 'getIsGlobal');
    }

    /**
     * @return Role[]
     */
    private function filterRolesInOrganizationContext(): array
    {
        $organizationId = $this->organizationContext->getId();
        $roles = parent::getRoles();
        foreach ($roles as $key => $role) {
            $roleOrganization = $role->getOrganization();
            if ($roleOrganization && $roleOrganization->getId() !== $organizationId) {
                unset($roles[$key]);
            }
        }

        return $roles;
    }
}
