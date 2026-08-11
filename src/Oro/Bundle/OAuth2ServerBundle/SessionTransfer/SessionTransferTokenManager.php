<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\IssuedSessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;

/**
 * Issues, persists and exposes short-lived Session Transfer Tokens.
 */
class SessionTransferTokenManager
{
    private const string TOKEN_PREFIX = 'stt_';

    public function __construct(
        private readonly ManagerRegistry $doctrine
    ) {
    }

    public function createToken(
        Client $client,
        string $sourceAccessTokenIdentifier,
        string $userIdentifier,
        SessionTransferTarget $target,
        \DateInterval $ttl
    ): IssuedSessionTransferToken {
        $this->validateClientAndTarget($client, $target);

        $plainToken = $this->generatePlainToken();
        $identifier = \hash('sha256', $plainToken);

        $createdAtImmutable = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAtImmutable = $createdAtImmutable->add($ttl);
        $createdAt = \DateTime::createFromImmutable($createdAtImmutable);
        $expiresAt = \DateTime::createFromImmutable($expiresAtImmutable);

        $transferToken = new SessionTransferToken();
        $transferToken
            ->setIdentifier($identifier)
            ->setClient($client)
            ->setSourceAccessTokenIdentifier($sourceAccessTokenIdentifier)
            ->setUserIdentifier($userIdentifier)
            ->setOrganization($client->getOrganization())
            ->setRoute($target->getRoute())
            ->setRouteParameters($target->getRouteParameters())
            ->setContextData($target->getContextData())
            ->setCreatedAt($createdAt)
            ->setExpiresAt($expiresAt)
            ->setConsumedAt(null)
            ->setRevoked(false);

        $client->setLastUsedAt($createdAt);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($transferToken);
        $entityManager->flush();

        $expiresIn = \max(0, $expiresAtImmutable->getTimestamp() - $createdAtImmutable->getTimestamp());

        return new IssuedSessionTransferToken(
            $plainToken,
            $expiresIn,
            $transferToken
        );
    }

    private function validateClientAndTarget(
        Client $client,
        SessionTransferTarget $target
    ): void {
        if (!$client->isActive()) {
            throw OAuthServerException::invalidGrant('The OAuth application is inactive.');
        }

        if (!$client->isSessionTransferAllowed()) {
            throw OAuthServerException::invalidGrant('Session Transfer is not enabled for this OAuth application.');
        }

        $organization = $client->getOrganization();
        if (null === $organization) {
            throw OAuthServerException::invalidGrant('The OAuth application is not assigned to an organization.');
        }

        if (!$organization->isEnabled()) {
            throw OAuthServerException::invalidGrant('The OAuth application organization is disabled.');
        }

        if ('' === $target->getRoute()) {
            throw OAuthServerException::invalidRequest('route', 'The target route must not be empty.');
        }
    }

    private function generatePlainToken(): string
    {
        return self::TOKEN_PREFIX . $this->encode(\random_bytes(32));
    }

    private function encode(string $value): string
    {
        return \rtrim(\strtr(\base64_encode($value), '+/', '-_'), '=');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->doctrine->getManagerForClass(SessionTransferToken::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException(
                \sprintf('The entity manager for "%s" is not available.', SessionTransferToken::class)
            );
        }

        return $entityManager;
    }
}
