<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Provider;

use Doctrine\Common\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\UserBundle\Entity\AbstractUser;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The authentication provider that does the verification of OAuth 2.0 token.
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

    /**
     * @param UserProviderInterface           $userProvider
     * @param string                          $providerKey
     * @param AuthorizationValidatorInterface $authorizationValidator
     * @param ManagerRegistry                 $doctrine
     * @param UserCheckerInterface            $userChecker
     */
    public function __construct(
        UserProviderInterface $userProvider,
        string $providerKey,
        AuthorizationValidatorInterface $authorizationValidator,
        ManagerRegistry $doctrine,
        UserCheckerInterface $userChecker
    ) {
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->authorizationValidator = $authorizationValidator;
        $this->doctrine = $doctrine;
        $this->userChecker = $userChecker;
    }

    /**
     * {@inheritdoc}
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

        $clientId = $request->getAttribute('oauth_client_id');
        /** @var Client $client */
        $client = $this->doctrine->getRepository(Client::class)->findOneBy(['identifier' => $clientId]);
        if (!$client->isActive()) {
            throw new AuthenticationException('Client is not active.');
        }

        $ownerClass = $client->getOwnerEntityClass();
        if (!$this->userProvider->supportsClass($ownerClass)) {
            throw new AuthenticationException('Wrong client owner type.');
        }

        /** @var AbstractUser $user */
        $user = $this->doctrine->getRepository($client->getOwnerEntityClass())->find($client->getOwnerEntityId());
        if (!$user) {
            throw new AuthenticationException('Client owner was not found.');
        }
        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);

        $organization = $client->getOrganization();
        if (!$organization->isEnabled()) {
            throw new AuthenticationException('Organization is not enabled.');
        }
        if (!$user->getOrganizations()->contains($organization)) {
            throw new AuthenticationException('User is not set to given organization.');
        }

        return new OAuth2Token($user, $organization);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token): bool
    {
        return
            $token instanceof OAuth2Token
            && $this->providerKey === $token->getAttribute(OAuth2Token::PROVIDER_KEY_ATTRIBUTE);
    }
}
