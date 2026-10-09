<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Owner\CustomerAwareOwnerTreeInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;

/**
 * Validates that a user-bound access token is issued within the owner scope of a storefront OAuth application.
 */
class ClientOwnerScopeValidator
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly UserLoaderInterface $userLoader,
        private readonly CustomerAwareOwnerTreeInterface $ownerTreeProvider
    ) {
    }

    public function isValid(Client $client, ?string $userIdentifier): bool
    {
        if (
            null === $userIdentifier
            || '' === $userIdentifier
            || null === $client->getOwnerEntityClass()
            || null === $client->getOwnerEntityId()
        ) {
            return true;
        }

        $organizationId = $client->getOrganization()?->getId();
        if (null === $organizationId) {
            return false;
        }

        $owner = $this->doctrine
            ->getRepository($client->getOwnerEntityClass())
            ->find($client->getOwnerEntityId());
        $user = $this->userLoader->loadUser($userIdentifier);
        if (!$owner instanceof CustomerUser || !$user instanceof CustomerUser || null === $owner->getCustomer()) {
            return false;
        }

        $tree = $this->ownerTreeProvider->getTreeByBusinessUnit($owner->getCustomer());
        $allowedCustomerIds = $tree->getUserSubordinateBusinessUnitIds($owner->getId(), $organizationId);
        $userCustomerIds = $tree->getUserBusinessUnitIds($user->getId(), $organizationId);

        return [] !== \array_intersect($allowedCustomerIds, $userCustomerIds);
    }
}
