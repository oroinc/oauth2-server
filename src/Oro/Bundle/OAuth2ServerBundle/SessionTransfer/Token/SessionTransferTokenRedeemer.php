<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Token;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * Validates and atomically marks a Session Transfer Token as consumed.
 */
class SessionTransferTokenRedeemer
{
    private const string TOKEN_PREFIX = 'stt_';
    private const int MAX_TOKEN_LENGTH = 128;

    public function __construct(
        private readonly ManagerRegistry $doctrine
    ) {
    }

    public function redeem(string $plainToken): SessionTransferToken
    {
        $this->validateTokenFormat($plainToken);
        $entityManager = $this->getEntityManager();
        $identifier = \hash('sha256', $plainToken);

        /** @var SessionTransferToken|null $transferToken */
        $transferToken = $entityManager
            ->getRepository(SessionTransferToken::class)
            ->findOneBy(['identifier' => $identifier]);

        if (null === $transferToken) {
            throw new GoneHttpException('The Session Transfer Token is invalid or has expired.');
        }

        $this->validateApplication($transferToken);
        $this->redeemAtomically($entityManager, $transferToken);

        return $transferToken;
    }

    private function validateTokenFormat(string $plainToken): void
    {
        if ('' === $plainToken) {
            throw new BadRequestHttpException('The Session Transfer Token is missing.');
        }

        if (\strlen($plainToken) > self::MAX_TOKEN_LENGTH) {
            throw new BadRequestHttpException('The Session Transfer Token has an invalid format.');
        }

        if (!\str_starts_with($plainToken, self::TOKEN_PREFIX)) {
            throw new BadRequestHttpException('The Session Transfer Token has an invalid format.');
        }
    }

    private function validateApplication(SessionTransferToken $transferToken): void
    {
        $client = $transferToken->getClient();

        if (null === $client) {
            throw new GoneHttpException('The Session Transfer Token does not have an OAuth application.');
        }

        if (!$client->isActive()) {
            throw new GoneHttpException('The OAuth application is inactive.');
        }

        if (!$client->isSessionTransferAllowed()) {
            throw new GoneHttpException('Session Transfer is no longer enabled for this OAuth application.');
        }

        $organization = $transferToken->getOrganization();

        if (null === $organization) {
            throw new GoneHttpException('The Session Transfer Token does not have an organization.');
        }

        if (!$organization->isEnabled()) {
            throw new GoneHttpException('The Session Transfer Token organization is disabled.');
        }
    }

    private function redeemAtomically(EntityManagerInterface $entityManager, SessionTransferToken $transferToken): void
    {
        $nowImmutable = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = \DateTime::createFromImmutable($nowImmutable);
        $affectedRows = $entityManager
            ->createQueryBuilder()
            ->update(SessionTransferToken::class, 'transferToken')
            ->set('transferToken.consumedAt', ':consumedAt')
            ->where('transferToken.id = :id')
            ->andWhere('transferToken.consumedAt IS NULL')
            ->andWhere('transferToken.revoked = :revoked')
            ->andWhere('transferToken.expiresAt > :now')
            ->setParameter('id', $transferToken->getId())
            ->setParameter('consumedAt', $now)
            ->setParameter('revoked', false)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();

        if (1 !== $affectedRows) {
            throw new GoneHttpException('The Session Transfer Token has expired or has already been used.');
        }

        $transferToken->setConsumedAt($now);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->doctrine->getManagerForClass(SessionTransferToken::class);
    }
}
