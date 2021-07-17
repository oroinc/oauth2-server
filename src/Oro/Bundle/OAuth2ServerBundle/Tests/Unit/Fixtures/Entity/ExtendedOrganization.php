<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Fixtures\Entity;

use Oro\Bundle\OrganizationBundle\Entity\Organization;

class ExtendedOrganization extends Organization
{
    /** @var bool */
    private $isGlobal;

    public function getIsGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function setIsGlobal(bool $isGlobal): void
    {
        $this->isGlobal = $isGlobal;
    }
}
