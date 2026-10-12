<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\ClientOwnerScopeValidator;

/**
 * The access token repository that validates the owner scope of storefront OAuth applications.
 */
class FrontendAccessTokenRepository extends AccessTokenRepository
{
    public function __construct(
        ManagerRegistry $doctrine,
        private readonly ClientManager $clientManager,
        private readonly ClientOwnerScopeValidator $clientOwnerScopeValidator
    ) {
        parent::__construct($doctrine, $clientManager);
    }

    #[\Override]
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $clientEntity = $accessTokenEntity->getClient();
        if ($clientEntity instanceof ClientEntity && $clientEntity->isFrontend()) {
            $client = $this->clientManager->getClient($clientEntity->getIdentifier());
            if (
                null !== $client
                && !$this->clientOwnerScopeValidator->isValid($client, $accessTokenEntity->getUserIdentifier())
            ) {
                throw OAuthServerException::invalidGrant();
            }
        }

        parent::persistNewAccessToken($accessTokenEntity);
    }
}
