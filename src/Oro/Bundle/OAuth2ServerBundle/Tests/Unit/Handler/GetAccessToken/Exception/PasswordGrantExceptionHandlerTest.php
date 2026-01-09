<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Exception;

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception\PasswordGrantExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\FailedUserOAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\UserBundle\Exception\BadCredentialsException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class PasswordGrantExceptionHandlerTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ClientManager&MockObject $clientManager;
    private OAuth2Authenticator&MockObject $oAuth2Authenticator;
    private UserProviderInterface&MockObject $userProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->oAuth2Authenticator = $this->createMock(OAuth2Authenticator::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
    }

    private function mockClientManager(string $identifier, Client $client): void
    {
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($identifier)
            ->willReturn($client);
    }

    public function testHandleOnNonPasswordGrant(): void
    {
        $request = (new ServerRequest('GET', ''))->withParsedBody(['grant_type' => 'client']);
        $this->eventDispatcher->expects(self::never())
            ->method('dispatch');

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            null,
        );

        $handler->handle($request, OAuthServerException::accessDenied());
    }

    public function testHandleWithPreviousAuthenticationException(): void
    {
        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $expectedException = new BadCredentialsException('bad creds');
        $expectedException->setToken($token);

        $exception = new OAuthServerException(
            'test',
            10,
            'invalid_credentials',
            401,
            null,
            null,
            $expectedException
        );

        $symfonyRequest = new Request();
        $symfonyRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'testUser');
        $symfonyRequest->attributes->set('user', $this->userProvider->loadUserByIdentifier('testUser'));

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new LoginFailureEvent(
                    $expectedException,
                    $this->oAuth2Authenticator,
                    $symfonyRequest,
                    null,
                    'firewallName',
                ),
            );

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            null,
        );

        $handler->handle($request, $exception);
    }


    public function testHandleWithOAuthBadCredentialsException(): void
    {
        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');
        $expectedException = new BadCredentialsException('test', 10, $exception);
        $expectedException->setToken($token);

        $symfonyRequest = new Request();
        $symfonyRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'testUser');
        $symfonyRequest->attributes->set('user', $this->userProvider->loadUserByIdentifier('testUser'));

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new LoginFailureEvent(
                    $expectedException,
                    $this->oAuth2Authenticator,
                    $symfonyRequest,
                    null,
                    'firewallName',
                ),
            );

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            null,
        );

        $handler->handle($request, $exception);
    }

    public function testHandleWithNonOAuthBadCredentialsException(): void
    {
        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $exception = OAuthServerException::accessDenied();
        $expectedException = new AuthenticationException(
            'The resource owner or authorization server denied the request.',
            9,
            $exception
        );
        $expectedException->setToken($token);

        $symfonyRequest = new Request();
        $symfonyRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'testUser');
        $symfonyRequest->attributes->set('user', $this->userProvider->loadUserByIdentifier('testUser'));

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new LoginFailureEvent(
                    $expectedException,
                    $this->oAuth2Authenticator,
                    $symfonyRequest,
                    null,
                    'firewallName',
                ),
            );

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            null,
        );

        $handler->handle($request, $exception);
    }

    public function testHandleWithOAuthBadCredentialsExceptionAndFronendRequest(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $client = new Client();
        $client->setFrontend(true);

        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser', 'client_id' => 'test_client'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');
        $expectedException = new BadCredentialsException('test', 10, $exception);
        $expectedException->setToken($token);

        $symfonyRequest = new Request();
        $symfonyRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'testUser');
        $symfonyRequest->attributes->set('user', $this->userProvider->loadUserByIdentifier('testUser'));

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new LoginFailureEvent(
                    $expectedException,
                    $this->oAuth2Authenticator,
                    $symfonyRequest,
                    null,
                    'firewallName',
                ),
            );

        $this->mockClientManager('test_client', $client);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateFrontendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            $frontendHelper,
        );
        $handler->handle($request, $exception);
    }

    public function testHandleWithOAuthBadCredentialsExceptionAndBackendRequest(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $client = new Client();
        $client->setFrontend(false);

        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser', 'client_id' => 'test_client'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');
        $expectedException = new BadCredentialsException('test', 10, $exception);
        $expectedException->setToken($token);

        $symfonyRequest = new Request();
        $symfonyRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'testUser');
        $symfonyRequest->attributes->set('user', $this->userProvider->loadUserByIdentifier('testUser'));

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new LoginFailureEvent(
                    $expectedException,
                    $this->oAuth2Authenticator,
                    $symfonyRequest,
                    null,
                    'firewallName',
                ),
            );

        $this->mockClientManager('test_client', $client);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            $frontendHelper,
        );

        $handler->handle($request, $exception);
    }

    public function testHandleShouldRestoreFrontendHelperStateOnExceptionDuringEventDispatching(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $exception = new \Exception('some error');
        $this->expectException(get_class($exception));

        $client = new Client();
        $client->setFrontend(false);

        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser', 'client_id' => 'test_client'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->mockClientManager('test_client', $client);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');

        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            $frontendHelper,
        );

        $handler->handle($request, $exception);
    }

    public function testWithWrongClientId(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $tokenParameters = ['grant_type' => 'password', 'username' => 'user', 'client_id' => 'test_client'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('user');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');
        $expectedException = new BadCredentialsException('test', 10, $exception);
        $expectedException->setToken($token);

        $symfonyRequest = new Request();
        $symfonyRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'user');
        $symfonyRequest->attributes->set('user', $this->userProvider->loadUserByIdentifier('user'));

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new LoginFailureEvent(
                    $expectedException,
                    $this->oAuth2Authenticator,
                    $symfonyRequest,
                    null,
                    'firewallName',
                ),
            );

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn(null);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler(
            $this->eventDispatcher,
            $this->clientManager,
            $this->oAuth2Authenticator,
            $this->userProvider,
            $frontendHelper,
        );

        $handler->handle($request, $exception);
    }
}
