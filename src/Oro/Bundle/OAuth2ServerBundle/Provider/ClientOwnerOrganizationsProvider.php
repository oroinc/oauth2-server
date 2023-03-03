<?php

namespace Oro\Bundle\OAuth2ServerBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityExtendBundle\EntityPropertyInfo;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\UserBundle\Entity\AbstractUser;

/**
 * A helper class that can be used to get organizations to which OAuth 2.0 client owner belongs to.
 */
class ClientOwnerOrganizationsProvider
{
    /** @var ManagerRegistry */
    private $doctrine;

    /** @var TokenAccessorInterface */
    private $tokenAccessor;

    public function __construct(ManagerRegistry $doctrine, TokenAccessorInterface $tokenAccessor)
    {
        $this->doctrine = $doctrine;
        $this->tokenAccessor = $tokenAccessor;
    }

    /**
     * Checks whether an application supports multi organizations.
     */
    public function isMultiOrganizationSupported(): bool
    {
        $organization = $this->tokenAccessor->getOrganization();

        return
            null !== $organization
            && $this->hasIsGlobalMethod($organization);
    }

    /**
     * Gets organizations to which OAuth 2.0 client owner belongs to.
     * All organizations are returned only if a user is logged in a global organization;
     * otherwise only an organization from the security context is returned.
     *
     * @param string $ownerEntityClass
     * @param int    $ownerEntityId
     *
     * @return Organization[]
     */
    public function getClientOwnerOrganizations(string $ownerEntityClass, int $ownerEntityId): array
    {
        $currentOrganization = $this->getCurrentOrganization();
        if (!$this->hasIsGlobalMethod($currentOrganization) || !$currentOrganization->getIsGlobal()) {
            return [$currentOrganization];
        }

        $owner = $this->doctrine->getManagerForClass($ownerEntityClass)->find($ownerEntityClass, $ownerEntityId);
        if ($owner instanceof AbstractUser) {
            return $owner->getOrganizations(true)->toArray();
        }

        return [$currentOrganization];
    }

    /**
     * Checks the given list of organizations to which OAuth 2.0 client owner can belong to
     * and decide whether the organization selector should be displayed for the current logged in user or not.
     *
     * @param Organization[] $organizations
     *
     * @return bool
     */
    public function isOrganizationSelectorRequired(array $organizations): bool
    {
        if (count($organizations) > 1) {
            return true;
        }

        return
            count($organizations) === 1
            && $organizations[0]->getId() !== $this->tokenAccessor->getOrganizationId();
    }

    /**
     * Sorts the given organizations to use them in a choice form type that shows a list of organizations.
     * An organization from the security context is returned as the first element of the array,
     * all other organizations are sorted alphabetically by name.
     *
     * @param Organization[] $organizations
     *
     * @return Organization[]
     */
    public function sortOrganizations(array $organizations): array
    {
        $result = [];
        $currentOrgId = $this->getCurrentOrganization()->getId();
        $currentOrg = null;
        foreach ($organizations as $org) {
            if ($org->getId() === $currentOrgId) {
                $currentOrg = $org;
            } else {
                $result[] = $org;
            }
        }
        uasort($result, function (Organization $a, Organization $b) {
            return strcasecmp($a->getName(), $b->getName());
        });
        if (null !== $currentOrg) {
            $result = array_merge([$currentOrg], $result);
        }

        return $result;
    }

    private function getCurrentOrganization(): Organization
    {
        $organization = $this->tokenAccessor->getOrganization();
        if (null === $organization) {
            throw new \LogicException('The security context must have an organization.');
        }

        return $organization;
    }

    private function hasIsGlobalMethod(Organization $organization): bool
    {
        return EntityPropertyInfo::methodExists($organization, 'getIsGlobal');
    }
}
