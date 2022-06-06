<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Provider;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The authentication provider for requests with OAuth2 authentication headers.
 */
class OAuth2Provider implements AuthenticationProviderInterface
{
    /** @var UserProviderInterface */
    private $userProvider;

    /** @var string */
    private $providerKey;

    /** @var AuthorizationValidatorInterface */
    private $authorizationValidator;

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var UserCheckerInterface */
    private $userChecker;

    /** @var ClientManager */
    private $clientManager;

    public function __construct(
        UserProviderInterface $userProvider,
        string $providerKey,
        AuthorizationValidatorInterface $authorizationValidator,
        ManagerRegistry $doctrine,
        UserCheckerInterface $userChecker,
        ClientManager $clientManager
    ) {
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->authorizationValidator = $authorizationValidator;
        $this->doctrine = $doctrine;
        $this->userChecker = $userChecker;
        $this->clientManager = $clientManager;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(TokenInterface $token): bool
    {
        return
            $token instanceof OAuth2Token
            && $this->providerKey === $token->getAttribute(OAuth2Token::PROVIDER_KEY_ATTRIBUTE);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(TokenInterface $token): TokenInterface
    {
        try {
            /**
             * Validates the request.
             * If an access token in "Authorization" header is valid, the following additional attributes are set:
             *  - oauth_access_token_id
             *  - oauth_client_id
             *  - oauth_user_id
             *  - oauth_scopes
             */
            $request = $this->authorizationValidator->validateAuthorization(
                $token->getAttribute(OAuth2Token::REQUEST_ATTRIBUTE)
            );
        } catch (OAuthServerException $e) {
            throw new AuthenticationException('The resource server rejected the request.', 0, $e);
        }

        $client = $this->getClient($request->getAttribute('oauth_client_id'));
        $organization = $client->getOrganization();
        if (!$organization->isEnabled()) {
            throw new AuthenticationException('The OAuth client organization is not enabled.');
        }

        return $this->validateUserAndGetToken($this->getUser($client, $request), $organization);
    }

    private function getClient(?string $clientId): Client
    {
        if (!$clientId) {
            throw new AuthenticationException('The OAuth client ID is not specified.');
        }

        $client = $this->clientManager->getClient($clientId);

        if (null === $client) {
            throw new AuthenticationException('The OAuth client does not exist.');
        }
        if (!$client->isActive()) {
            throw new AuthenticationException('The OAuth client is not active.');
        }

        return $client;
    }

    private function getUser(Client $client, ServerRequestInterface $request): object
    {
        $ownerClass = $client->getOwnerEntityClass();
        $ownerId = $client->getOwnerEntityId();

        if (null !== $ownerClass && null !== $ownerId) {
            $this->validateOwnerClass($ownerClass);
            $user = $this->doctrine->getRepository($ownerClass)->find($ownerId);
            if (null === $user) {
                throw new AuthenticationException(\sprintf(
                    'The user does not exist. Class: %s. Id: %s',
                    $ownerClass,
                    $ownerId
                ));
            }
        } else {
            $ownerClass = $client->isFrontend()
                ? CustomerUser::class
                : User::class;
            $this->validateOwnerClass($ownerClass);
            $user = $this->userProvider->loadUserByUsername($request->getAttribute('oauth_user_id'));
        }

        return $user;
    }

    protected function validateUserAndGetToken(object $user, Organization $organization): TokenInterface
    {
        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);
        if (!$user->isBelongToOrganization($organization)) {
            throw new AuthenticationException('The user is not set to given OAuth client organization.');
        }

        return new OAuth2Token($user, $organization);
    }

    private function validateOwnerClass(string $ownerClass): void
    {
        if (!$this->userProvider->supportsClass($ownerClass)) {
            throw new AuthenticationException(\sprintf(
                'The user provider does not support users of the type "%s".',
                $ownerClass
            ));
        }
    }
}
