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

class OAuth2AuthenticatorTest extends TestCase
{
    private LoggerInterface $loggerMock;
    private ClientManager $clientManagerMock;
    private ManagerRegistry $doctrineMock;
    private UserProviderInterface $userProviderMock;
    private AuthorizationValidatorInterface $authorizationValidatorMock;
    private HttpMessageFactoryInterface $httpMessageFactoryMock;
    private AnonymousCustomerUserRolesProvider $rolesProviderMock;
    private FeatureDependAuthenticatorChecker $featureDependAuthenticationChecker;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->clientManagerMock = $this->createMock(ClientManager::class);
        $this->doctrineMock = $this->createMock(ManagerRegistry::class);
        $this->userProviderMock = $this->createMock(UserProviderInterface::class);
        $this->authorizationValidatorMock = $this->createMock(AuthorizationValidatorInterface::class);
        $this->httpMessageFactoryMock = $this->createMock(HttpMessageFactoryInterface::class);
        $this->rolesProviderMock = $this->createMock(AnonymousCustomerUserRolesProvider::class);
        $this->featureDependAuthenticationChecker = $this->createMock(FeatureDependAuthenticatorChecker::class);
    }

    public function testSupports(): void
    {
        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $this->httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someToken');
        $this->featureDependAuthenticationChecker
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        self::assertTrue($authenticator->supports($request));
    }

    public function testAuthenticate(): void
    {
        $serverRequestMock = $this->createMock(ServerRequestInterface::class);
        $serverRequestMock
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser',
                ];

                return $attributes[$attribute] ?? null;
            });

        $this->authorizationValidatorMock = $this->createMock(AuthorizationValidatorInterface::class);
        $this->authorizationValidatorMock
            ->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequestMock);

        $this->userProviderMock
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $clientMock = $this->createMock(Client::class);
        $clientMock
            ->method('isActive')->willReturn(true);
        $clientMock
            ->method('getOrganization')->willReturn(new Organization());
        $this->clientManagerMock
            ->method('getClient')->willReturn($clientMock);

        $userMock = $this->createMock(User::class);
        $userMock
            ->method('getUsername')->willReturn('testUser');
        $userMock
            ->method('isBelongToOrganization')->willReturn(true);
        $this->userProviderMock
            ->method('loadUserByIdentifier')->willReturn($userMock);

        $httpMessageFactoryMock = $this->createMock(HttpMessageFactoryInterface::class);
        $httpMessageFactoryMock
            ->method('createRequest')->willReturn($serverRequestMock);

        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someToken');

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(Passport::class, $passport);
    }

    public function testAuthenticateWhenHasAuthorizationCookie(): void
    {
        $serverRequestMock = $this->createMock(ServerRequestInterface::class);
        $serverRequestMock
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser',
                ];

                return $attributes[$attribute] ?? null;
            });

        $authorizationCookie = 'Bearer someToken';
        $serverRequestMock
            ->expects(self::once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer ' . $authorizationCookie)
            ->willReturn($serverRequestMock);

        $this->authorizationValidatorMock = $this->createMock(AuthorizationValidatorInterface::class);
        $this->authorizationValidatorMock
            ->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequestMock);

        $this->userProviderMock
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $clientMock = $this->createMock(Client::class);
        $clientMock
            ->method('isActive')->willReturn(true);
        $clientMock
            ->method('getOrganization')->willReturn(new Organization());
        $this->clientManagerMock
            ->method('getClient')->willReturn($clientMock);

        $userMock = $this->createMock(User::class);
        $userMock
            ->method('getUsername')->willReturn('testUser');
        $userMock
            ->method('isBelongToOrganization')->willReturn(true);
        $this->userProviderMock
            ->method('loadUserByIdentifier')->willReturn($userMock);

        $httpMessageFactoryMock = $this->createMock(HttpMessageFactoryInterface::class);
        $httpMessageFactoryMock
            ->method('createRequest')->willReturn($serverRequestMock);

        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $request = new Request();
        $request->cookies->set('AUTH1', 'Bearer someToken');

        $authenticator->setAuthorizationCookies(['AUTH1', 'AUTH2']);
        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(Passport::class, $passport);
    }

    public function testAuthenticateWhenHasEmptyAuthorizationCookies(): void
    {
        $serverRequestMock = $this->createMock(ServerRequestInterface::class);
        $serverRequestMock
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser',
                ];

                return $attributes[$attribute] ?? null;
            });

        $this->authorizationValidatorMock = $this->createMock(AuthorizationValidatorInterface::class);
        $this->authorizationValidatorMock
            ->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequestMock);

        $this->userProviderMock
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $clientMock = $this->createMock(Client::class);
        $clientMock
            ->method('isActive')->willReturn(true);
        $clientMock
            ->method('getOrganization')->willReturn(new Organization());
        $this->clientManagerMock
            ->method('getClient')->willReturn($clientMock);

        $userMock = $this->createMock(User::class);
        $userMock
            ->method('getUsername')->willReturn('testUser');
        $userMock
            ->method('isBelongToOrganization')->willReturn(true);
        $this->userProviderMock
            ->method('loadUserByIdentifier')->willReturn($userMock);

        $httpMessageFactoryMock = $this->createMock(HttpMessageFactoryInterface::class);
        $httpMessageFactoryMock
            ->method('createRequest')->willReturn($serverRequestMock);

        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someToken');

        $authenticator->setAuthorizationCookies(['AUTH1', 'AUTH2']);
        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(Passport::class, $passport);
    }

    public function testAuthenticatePrefersCookieWhenHasBothAuthorizationHeaderAndCookie(): void
    {
        $serverRequestMock = $this->createMock(ServerRequestInterface::class);
        $serverRequestMock
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) {
                $attributes = [
                    'oauth_access_token_id' => 'some_access_token_id',
                    'oauth_client_id' => 'some_client_id',
                    'oauth_user_id' => 'testUser',
                ];

                return $attributes[$attribute] ?? null;
            });

        $authorizationCookie = 'Bearer someToken';
        $serverRequestMock
            ->expects(self::once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer ' . $authorizationCookie)
            ->willReturn($serverRequestMock);

        $this->authorizationValidatorMock = $this->createMock(AuthorizationValidatorInterface::class);
        $this->authorizationValidatorMock
            ->expects(self::once())
            ->method('validateAuthorization')
            ->willReturn($serverRequestMock);

        $this->userProviderMock
            ->method('supportsClass')
            ->willReturnCallback(function ($class) {
                return $class === User::class;
            });

        $clientMock = $this->createMock(Client::class);
        $clientMock
            ->method('isActive')->willReturn(true);
        $clientMock
            ->method('getOrganization')->willReturn(new Organization());
        $this->clientManagerMock
            ->method('getClient')->willReturn($clientMock);

        $userMock = $this->createMock(User::class);
        $userMock
            ->method('getUsername')->willReturn('testUser');
        $userMock
            ->method('isBelongToOrganization')->willReturn(true);
        $this->userProviderMock
            ->method('loadUserByIdentifier')->willReturn($userMock);

        $httpMessageFactoryMock = $this->createMock(HttpMessageFactoryInterface::class);
        $httpMessageFactoryMock
            ->method('createRequest')->willReturn($serverRequestMock);

        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer someInvalidToken');
        $request->cookies->set('AUTH1', 'Bearer someToken');

        $authenticator->setAuthorizationCookies(['AUTH1', 'AUTH2']);
        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(Passport::class, $passport);
    }

    public function testCreateOAuth2Token(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock
            ->method('getUsername')->willReturn('testUser');
        $organizationMock = $this->createMock(Organization::class);

        $passport = new SelfValidatingPassport(
            new UserBadge('testUser', function () use ($userMock) {
                return $userMock;
            })
        );
        $passport->setAttribute('organization', $organizationMock);


        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $this->httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $token = $authenticator->createToken($passport, 'firewallName');

        self::assertInstanceOf(OAuth2Token::class, $token);
    }

    public function testCreateAnonymousCustomerUserToken(): void
    {
        $user = new CustomerVisitor();
        $organization = $this->createMock(Organization::class);
        $roles = ['ROLE_ANONYMOUS'];

        $userBadge = new UserBadge('username', function () use ($user) {
            return $user;
        });

        $passport = new SelfValidatingPassport($userBadge);
        $passport->setAttribute('organization', $organization);

        $rolesProviderMock = $this->createMock(AnonymousCustomerUserRolesProvider::class);
        $rolesProviderMock
            ->method('getRoles')->willReturn($roles);

        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $this->httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $rolesProviderMock
        );

        $resultToken = $authenticator->createToken($passport, 'firewallName');

        self::assertInstanceOf(AnonymousCustomerUserToken::class, $resultToken);
        self::assertEquals($roles, $resultToken->getRoles());
    }

    public function testOnAuthenticationFailure(): void
    {
        $requestMock = $this->createMock(Request::class);
        $exceptionMock = $this->createMock(AuthenticationException::class);

        $expectedResponse = new Response('', 401, []);

        $authenticator = new OAuth2Authenticator(
            $this->loggerMock,
            $this->clientManagerMock,
            $this->doctrineMock,
            $this->userProviderMock,
            $this->authorizationValidatorMock,
            $this->httpMessageFactoryMock,
            $this->featureDependAuthenticationChecker,
            'test',
            $this->rolesProviderMock
        );

        $response = $authenticator->onAuthenticationFailure($requestMock, $exceptionMock);

        self::assertEquals($expectedResponse, $response);
    }
}
