<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves the user identifier from an OAuth access token or its client owner.
 */
class SessionTransferSubjectResolver
{
    public function __construct(
        private readonly ManagerRegistry $doctrine
    ) {
    }

    public function resolveUserIdentifier(AccessToken $accessToken): string
    {
        $userIdentifier = $accessToken->getUserIdentifier();
        if (\is_string($userIdentifier) && '' !== \trim($userIdentifier)) {
            return $userIdentifier;
        }

        return $this->resolveClientOwnerIdentifier($accessToken->getClient());
    }

    private function resolveClientOwnerIdentifier(?Client $client): string
    {
        if (null === $client) {
            throw OAuthServerException::invalidGrant('The source access token does not have an OAuth application.');
        }

        $ownerClass = $client->getOwnerEntityClass();
        $ownerId = $client->getOwnerEntityId();
        if (!\is_string($ownerClass) || '' === $ownerClass || null === $ownerId) {
            throw OAuthServerException::invalidGrant(
                'The source access token is not bound to a user and '
                . 'the OAuth application does not have an owner.'
            );
        }

        if (!\is_a($ownerClass, UserInterface::class, true)) {
            throw OAuthServerException::invalidGrant(
                \sprintf(
                    'The OAuth application owner class "%s" does not implement "%s".',
                    $ownerClass,
                    UserInterface::class
                )
            );
        }

        $manager = $this->doctrine->getManagerForClass($ownerClass);
        if (null === $manager) {
            throw OAuthServerException::invalidGrant(
                \sprintf('No Doctrine manager is available for the OAuth application owner "%s".', $ownerClass)
            );
        }

        $owner = $manager->getRepository($ownerClass)->find($ownerId);
        if (!$owner instanceof UserInterface) {
            throw OAuthServerException::invalidGrant('The OAuth application owner cannot be found.');
        }

        $userIdentifier = $owner->getUserIdentifier();
        $this->validateOwnerContext($client, $owner);

        return $userIdentifier;
    }

    private function validateOwnerContext(
        Client $client,
        UserInterface $owner
    ): void {
        if ($client->isFrontend()) {
            if (!\str_starts_with($owner::class, 'Oro\\Bundle\\CustomerBundle\\')) {
                throw OAuthServerException::invalidGrant(
                    'A storefront OAuth application must be owned by a customer user.'
                );
            }

            return;
        }

        if (!\is_a($owner, UserInterface::class)) {
            throw OAuthServerException::invalidGrant(
                'A back-office OAuth application must be owned by a back-office user.'
            );
        }
    }
}
