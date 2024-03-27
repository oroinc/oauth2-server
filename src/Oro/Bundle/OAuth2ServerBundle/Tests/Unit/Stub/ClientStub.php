<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Stub;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

class ClientStub extends Client
{
    private ?CustomerUser $customerUser = null;

    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }

    public function getCustomerUser(): ?CustomerUser
    {
        return $this->customerUser;
    }

    public function setCustomerUser(?CustomerUser $customerUser): self
    {
        $this->customerUser = $customerUser;

        return $this;
    }
}
