<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model;

use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;

/**
 * Represents the persisted Session Transfer Token together with its public response values.
 */
final readonly class IssuedSessionTransferToken
{
    public function __construct(
        private string $token,
        private int $expiresIn,
        private SessionTransferToken $entity
    ) {
        if ('' === $token) {
            throw new \InvalidArgumentException('The Session Transfer Token must not be empty.');
        }

        if ($expiresIn <= 0) {
            throw new \InvalidArgumentException('The Session Transfer Token lifetime must be greater than zero.');
        }
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getEntity(): SessionTransferToken
    {
        return $this->entity;
    }
}
