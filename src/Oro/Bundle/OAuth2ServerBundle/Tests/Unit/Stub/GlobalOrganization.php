<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Stub;

use Oro\Bundle\OrganizationBundle\Entity\Organization;

class GlobalOrganization extends Organization
{
    /** @var bool */
    private $isGlobal = false;

    /**
     * @return bool
     */
    public function getIsGlobal()
    {
        return $this->isGlobal;
    }

    /**
     * @param bool $isGlobal
     */
    public function setIsGlobal($isGlobal)
    {
        $this->isGlobal = $isGlobal;
    }
}
