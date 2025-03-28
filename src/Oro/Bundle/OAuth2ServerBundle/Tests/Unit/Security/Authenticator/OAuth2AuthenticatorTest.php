<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authenticator;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Oro\Bundle\ApiBundle\Security\FeatureDependAuthenticatorChecker;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Bundle\CustomerBundle\Security\AnonymousCustomerUserRolesProvider;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OAuth2AuthenticatorTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ClientManager&MockObject $clientManager;
    private ManagerRegistry&MockObject $doctrine;
    private UserProviderInterface&MockObject $userProvider;
    private AuthorizationValidatorInterface&MockObject $authorizationValidator;
    private HttpMessageFactoryInterface&MockObject $httpMessageFactory;
    private FeatureDependAuthenticatorChecker&MockObject $featureDependAuthenticationChecker;
    private AnonymousCustomerUserRolesProvider&MockObject $rolesProvider;
    private OAuth2Authenticator $authenticator;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->authorizationValidator = $this->createMock(AuthorizationValidatorInterface::class);
        $this->httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        $this->featureDependAuthenticationChecker = $this->createMock(FeatureDependAuthenticatorChecker::class);
        $this->rolesProvider = $this->createMock(AnonymousCustomerUserRolesProvider::class);

        $this->authenticator = new OAuth2Authenticator(
            $this->logger,
            $this->clientManager,
            $this->doctrine,
            $this->userProvider,
            $this->authorizationValidator,
            $this->httpMessageFactory,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProvider
        );
    }

    public function testSupports(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someToken');

        $this->featureDependAuthenticationChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        self::assertTrue($this->authenticator->supports($request));
    }

    public function testAuthenticate(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $serverRequest->expects(self::any())
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser'
                ];

                return $attributes[$attribute] ?? null;
            });

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequest);

        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isActive')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new Organization());
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->willReturn($client);

        $user = $this->createMock(User::class);
        $user->expects(self::once())
            ->method('isBelongToOrganization')
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->willReturn($user);

        $this->httpMessageFactory->expects(self::once())
            ->method('createRequest')
            ->willReturn($serverRequest);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someToken');

        self::assertInstanceOf(Passport::class, $this->authenticator->authenticate($request));
    }

    public function testSupportsWithAccessTokenParameterInRequestBody(): void
    {
        $request = new Request();
        $request->request->add(['access_token' => 'someToken']);
        $request->headers->set('Content-type', 'application/vnd.api+json');

        $this->featureDependAuthenticationChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        self::assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsWithAccessTokenParameterInRequestBodyWithoutCorrectContentTypeHeader(): void
    {
        $request = new Request();
        $request->request->add(['access_token' => 'someToken']);
        $request->headers->set('Content-type', 'json');

        $this->featureDependAuthenticationChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        self::assertFalse($this->authenticator->supports($request));
    }

    public function testSupportsWithAccessTokenParameterInURI(): void
    {
        $request = new Request();
        $request->query->add(['access_token' => 'someToken']);

        $this->featureDependAuthenticationChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        self::assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticateWithAccessTokenParameterInRequestBody(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $serverRequest->expects(self::any())
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser'
                ];

                return $attributes[$attribute] ?? null;
            });

        $serverRequest->expects(self::once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer someToken')
            ->willReturn($serverRequest);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequest);

        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isActive')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new Organization());
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->willReturn($client);

        $user = $this->createMock(User::class);
        $user->expects(self::once())
            ->method('isBelongToOrganization')
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->willReturn($user);

        $this->httpMessageFactory->expects(self::once())
            ->method('createRequest')
            ->willReturn($serverRequest);

        $request = new Request();
        $request->request->add(['access_token' => 'someToken']);

        self::assertInstanceOf(Passport::class, $this->authenticator->authenticate($request));
    }

    public function testAuthenticateWhenHasAuthorizationCookie(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $serverRequest->expects(self::any())
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser'
                ];

                return $attributes[$attribute] ?? null;
            });

        $authorizationCookie = 'Bearer someToken';
        $serverRequest->expects(self::once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer ' . $authorizationCookie)
            ->willReturn($serverRequest);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequest);

        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isActive')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new Organization());
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->willReturn($client);

        $user = $this->createMock(User::class);
        $user->expects(self::once())
            ->method('isBelongToOrganization')
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->willReturn($user);

        $this->httpMessageFactory->expects(self::once())
            ->method('createRequest')
            ->willReturn($serverRequest);

        $request = new Request();
        $request->cookies->set('AUTH1', 'Bearer someToken');

        $this->authenticator->setAuthorizationCookies(['AUTH1', 'AUTH2']);
        self::assertInstanceOf(Passport::class, $this->authenticator->authenticate($request));
    }

    public function testAuthenticateWhenHasEmptyAuthorizationCookies(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $serverRequest->expects(self::any())
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser'
                ];

                return $attributes[$attribute] ?? null;
            });

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequest);

        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isActive')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new Organization());
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->willReturn($client);

        $user = $this->createMock(User::class);
        $user->expects(self::once())
            ->method('isBelongToOrganization')
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->willReturn($user);

        $this->httpMessageFactory->expects(self::once())
            ->method('createRequest')
            ->willReturn($serverRequest);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someToken');

        $this->authenticator->setAuthorizationCookies(['AUTH1', 'AUTH2']);
        self::assertInstanceOf(Passport::class, $this->authenticator->authenticate($request));
    }

    public function testAuthenticatePrefersCookieWhenHasBothAuthorizationHeaderAndCookie(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $serverRequest->expects(self::any())
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser'
                ];

                return $attributes[$attribute] ?? null;
            });

        $authorizationCookie = 'Bearer someToken';
        $serverRequest->expects(self::once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer ' . $authorizationCookie)
            ->willReturn($serverRequest);

        $this->authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequest);

        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isActive')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('getOrganization')
            ->willReturn(new Organization());
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->willReturn($client);

        $user = $this->createMock(User::class);
        $user->expects(self::once())
            ->method('isBelongToOrganization')
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->willReturn($user);

        $this->httpMessageFactory->expects(self::once())
            ->method('createRequest')
            ->willReturn($serverRequest);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someInvalidToken');
        $request->cookies->set('AUTH1', 'Bearer someToken');

        $this->authenticator->setAuthorizationCookies(['AUTH1', 'AUTH2']);
        self::assertInstanceOf(Passport::class, $this->authenticator->authenticate($request));
    }

    public function testCreateOAuth2Token(): void
    {
        $user = $this->createMock(User::class);
        $organization = $this->createMock(Organization::class);

        $passport = new SelfValidatingPassport(
            new UserBadge('testUser', function () use ($user) {
                return $user;
            })
        );
        $passport->setAttribute('organization', $organization);

        self::assertInstanceOf(OAuth2Token::class, $this->authenticator->createToken($passport, 'firewallName'));
    }

    public function testCreateAnonymousCustomerUserToken(): void
    {
        $user = new CustomerVisitor();
        $organization = $this->createMock(Organization::class);
        $roles = ['ROLE_ANONYMOUS'];

        $passport = new SelfValidatingPassport(new UserBadge('username', function () use ($user) {
            return $user;
        }));
        $passport->setAttribute('organization', $organization);

        $this->rolesProvider->expects(self::once())
            ->method('getRoles')
            ->willReturn($roles);

        $resultToken = $this->authenticator->createToken($passport, 'firewallName');

        self::assertInstanceOf(AnonymousCustomerUserToken::class, $resultToken);
        self::assertEquals($roles, $resultToken->getRoles());
    }

    public function testOnAuthenticationFailure(): void
    {
        $request = $this->createMock(Request::class);
        $exception = $this->createMock(AuthenticationException::class);

        self::assertEquals(
            new Response('', 401, []),
            $this->authenticator->onAuthenticationFailure($request, $exception)
        );
    }
}
