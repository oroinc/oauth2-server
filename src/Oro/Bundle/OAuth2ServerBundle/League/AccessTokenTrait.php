<?php

namespace Oro\Bundle\OAuth2ServerBundle\League;

use DateTime;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Copy of {@see League\OAuth2\Server\Entities\Traits\AccessTokenTrait} that
 * do not shows the deprecated messages to the user.
 *
 * (c) Alex Bilbie <hello@alexbilbie.com>
 *
 * @deprecated Platform 4.2 and later will have updated version of the league/oauth2-server package.
 */
trait AccessTokenTrait
{
    /**
     * Generate a JWT from the access token
     *
     * @param CryptKey $privateKey
     *
     * @return Token
     */
    public function convertToJWT(CryptKey $privateKey)
    {
        $currentErrorReporting = error_reporting(E_ERROR);
        $result = null;

        try {
            $result = (new Builder())
                ->setAudience($this->getClient()->getIdentifier())
                ->setId($this->getIdentifier(), true)
                ->setIssuedAt(new \DateTimeImmutable())
                ->setNotBefore(new \DateTimeImmutable())
                ->setExpiration($this->getExpiryDateTime()->getTimestamp())
                ->setSubject($this->getUserIdentifier())
                ->set('scopes', $this->getScopes())
                ->sign(new Sha256(), new Key($privateKey->getKeyPath(), $privateKey->getPassPhrase()))
                ->getToken();
        } finally {
            error_reporting($currentErrorReporting);
        }

        return $result;
    }

    /**
     * @return ClientEntityInterface
     */
    abstract public function getClient();

    /**
     * @return DateTime
     */
    abstract public function getExpiryDateTime();

    /**
     * @return string|int
     */
    abstract public function getUserIdentifier();

    /**
     * @return ScopeEntityInterface[]
     */
    abstract public function getScopes();
}
