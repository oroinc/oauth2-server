<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Factory;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;

/**
 * Represents a factory to create {@see ClientEntity} instance.
 */
interface ClientEntityFactoryInterface
{
    public function createClientEntity(Client $client): ClientEntity;
}
