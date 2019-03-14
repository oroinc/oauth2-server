<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Fixtures\Entity;

use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\Role;

class ExtendedRole extends Role
{
    /** @var Organization */
    private $organization;

    /**
     * @return Organization|null
     */
    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    /**
     * @param Organization $organization
     */
    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }
}
