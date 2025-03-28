<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authenticator;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\ApiBundle\Security\FeatureDependAuthenticatorChecker;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Security\AnonymousCustomerUserRolesProvider;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\AbstractUser;
use Oro\Bundle\UserBundle\Entity\User;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * OAuth2 authenticator
 */
class OAuth2Authenticator implements AuthenticatorInterface
{
    private const string ACCESS_TOKEN_PARAMETER = 'access_token';

    private array $authorizationCookies = [];

    public function __construct(
        private LoggerInterface $logger,
        private ClientManager $clientManager,
        private ManagerRegistry $doctrine,
        private UserProviderInterface $userProvider,
        private AuthorizationValidatorInterface $authorizationValidator,
        private HttpMessageFactoryInterface $httpMessageFactory,
        private FeatureDependAuthenticatorChecker $featureDependAuthenticatorChecker,
        private string $firewallName,
        private ?AnonymousCustomerUserRolesProvider $rolesProvider = null
    ) {
    }

    public function setAuthorizationCookies(array $authorizationCookies): void
    {
        $this->authorizationCookies = $authorizationCookies;
    }

    #[\Override]
    public function supports(Request $request): ?bool
    {
        if (!$this->featureDependAuthenticatorChecker->isEnabled($this, $this->firewallName)) {
            return false;
        }

        return
            (
                $request->headers->has('Authorization')
                && preg_match('/^(?:\s+)?Bearer\s/', $request->headers->get('Authorization'))
            )
            || ($this->authorizationCookies && $this->getAuthorizationCookie($request))
            || (
                $request->request->has(self::ACCESS_TOKEN_PARAMETER)
                && $request->headers->contains('Content-type', 'application/vnd.api+json')
            );
    }

    private function getAuthorizationCookie(Request $request): ?string
    {
        foreach ($this->authorizationCookies as $authorizationCookie) {
            $value = $request->cookies->get($authorizationCookie);
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $serverRequest = $this->validateAuthorization($this->createServerRequest($request));

        $client = $this->getClient($serverRequest->getAttribute('oauth_client_id'));
        $organization = $this->validateClientOrganization($client);

        $user = $this->getUser($client, $serverRequest);
        $this->validateUser($user, $organization);

        return $this->createPassport($user, $organization);
    }

    #[\Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();
        $organization = $passport->getAttribute('organization');

        if ($user instanceof AbstractUser) {
            return new OAuth2Token($user, $organization);
        }

        return new AnonymousCustomerUserToken(
            $user,
            $this->rolesProvider->getRoles(),
            $organization
        );
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('', 401, []);
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
            $user = $this->userProvider->loadUserByIdentifier($request->getAttribute('oauth_user_id'));
        }

        return $user;
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

    private function createServerRequest(Request $request): ServerRequestInterface
    {
        $serverRequest = $this->httpMessageFactory->createRequest($request);

        if ($this->authorizationCookies) {
            $authorizationCookie = $this->getAuthorizationCookie($request);
            if ($authorizationCookie) {
                $serverRequest = $serverRequest->withHeader('Authorization', 'Bearer ' . $authorizationCookie);
            }
        }

        if ($request->request->has(self::ACCESS_TOKEN_PARAMETER)) {
            $serverRequest = $serverRequest->withHeader(
                'Authorization',
                'Bearer ' . $request->request->get(self::ACCESS_TOKEN_PARAMETER)
            );
            $request->request->remove(self::ACCESS_TOKEN_PARAMETER);
        }

        return $serverRequest;
    }

    private function validateAuthorization(ServerRequestInterface $serverRequest): ServerRequestInterface
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
            $validatedRequest = $this->authorizationValidator->validateAuthorization($serverRequest);

            $requiredAttributes = ['oauth_access_token_id', 'oauth_client_id'];
            foreach ($requiredAttributes as $attribute) {
                if (!$validatedRequest->getAttribute($attribute)) {
                    $this->logger->info(
                        'Required attributes are missing or invalid.'
                        . implode(', ', $requiredAttributes)
                    );
                    throw new AuthenticationException('Required attributes are missing or invalid.');
                }
            }

            return $validatedRequest;
        } catch (OAuthServerException $e) {
            throw new AuthenticationException('The resource server rejected the request.', 0, $e);
        }
    }

    private function validateClientOrganization(Client $client): Organization
    {
        $organization = $client->getOrganization();
        if (!$organization->isEnabled()) {
            throw new AuthenticationException('The OAuth client organization is not enabled.');
        }

        return $organization;
    }

    private function validateUser(object $user, Organization $organization): void
    {
        if ($user instanceof AbstractUser && !$user->isBelongToOrganization($organization)) {
            throw new AuthenticationException('The user is not set to given OAuth client organization.');
        }
    }

    private function createPassport(object $user, Organization $organization): Passport
    {
        $passport = new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), function () use ($user) {
                return $user;
            })
        );

        $passport->setAttribute('organization', $organization);

        return $passport;
    }
}
