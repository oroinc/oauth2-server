<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\EntityExtendBundle\EntityPropertyInfo;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationAwareTokenInterface;
use Oro\Bundle\SecurityBundle\Authentication\Token\RolesAndOrganizationAwareTokenTrait;
use Oro\Bundle\SecurityBundle\Authentication\Token\RolesAwareTokenInterface;
use Oro\Bundle\UserBundle\Entity\Role;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * The token that represents OAuth 2.0 authentication data.
 */
class OAuth2Token extends AbstractToken implements OrganizationAwareTokenInterface, RolesAwareTokenInterface
{
    use RolesAndOrganizationAwareTokenTrait {
        getRoles as traitGetRoles;
    }

    public const REQUEST_ATTRIBUTE      = 'request';
    public const PROVIDER_KEY_ATTRIBUTE = 'provider_key';

    public function __construct(UserInterface $user = null, Organization $organization = null)
    {
        $this->setAuthenticated(false);

        if (null !== $user && null !== $organization) {
            $roles = $user->getUserRoles();

            parent::__construct($this->initRoles($roles));

            $this->setUser($user);
            $this->setOrganization($organization);

            $this->setAuthenticated(\count($roles) > 0);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles(): array
    {
        if (!$this->hasIsGlobalMethod($this->organization)) {
            return $this->traitGetRoles();
        }

        return $this->filterRolesByOrganization($this->organization, $this->traitGetRoles());
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
        return EntityPropertyInfo::methodExists($organization, 'getIsGlobal');
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
