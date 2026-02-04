<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\League\Factory;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;

/**
 * The factory to create {@see ClientEntity} instance.
 */
class ClientEntityFactory implements ClientEntityFactoryInterface
{
    #[\Override]
    public function createClientEntity(Client $client): ClientEntity
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($client->getIdentifier());
        $clientEntity->setName($client->getName());
        $clientEntity->setRedirectUri(array_values(array_filter($client->getRedirectUris())));
        $clientEntity->setFrontend($client->isFrontend());
        $clientEntity->setConfidential($client->isConfidential());
        $clientEntity->setPlainTextPkceAllowed($client->isPlainTextPkceAllowed());
        $clientEntity->setSkipAuthorizeClientAllowed($client->isSkipAuthorizeClientAllowed());

        return $clientEntity;
    }
}
