<?php

namespace Oro\Bundle\OAuth2ServerBundle\League;

use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The authorization validator that creates OAuth 2.0 resource server service
 * and delegates checking of the access token validity to it.
 * This class was created instead of direct registering of the resource server service in the container
 * because the creation of the resource server fails if the public key does not exist,
 * and as result, 500 (Internal Server Error) response status code is returned
 * instead of required 401 (Unauthorized) status code.
 */
class AuthorizationValidator implements AuthorizationValidatorInterface
{
    /** @var CryptKeyFile */
    private $publicKey;

    /** @var AccessTokenRepositoryInterface */
    private $accessTokenRepository;

    /** @var AuthorizationValidatorInterface|null */
    private $authorizationValidator;

    /** @var ResourceServer|null */
    private $resourceServer;

    public function __construct(
        CryptKeyFile $publicKey,
        AccessTokenRepositoryInterface $accessTokenRepository,
        AuthorizationValidatorInterface $authorizationValidator = null
    ) {
        $this->publicKey = $publicKey;
        $this->accessTokenRepository = $accessTokenRepository;
        $this->authorizationValidator = $authorizationValidator;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthorization(ServerRequestInterface $request)
    {
        return $this->getResourceServer()->validateAuthenticatedRequest($request);
    }

    private function getResourceServer(): ResourceServer
    {
        if (null === $this->resourceServer) {
            try {
                $publicKey = new CryptKey($this->publicKey->getKeyPath(), null, false);
            } catch (\LogicException $e) {
                throw CryptKeyNotFoundException::create($e);
            }

            $this->resourceServer = new ResourceServer(
                $this->accessTokenRepository,
                $publicKey,
                $this->authorizationValidator
            );
        }

        return $this->resourceServer;
    }
}
