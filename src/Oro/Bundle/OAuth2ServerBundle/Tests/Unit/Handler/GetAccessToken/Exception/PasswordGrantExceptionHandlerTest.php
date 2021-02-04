<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Exception;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception\PasswordGrantExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\FailedUserOAuth2Token;
use Oro\Bundle\UserBundle\Exception\BadCredentialsException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class PasswordGrantExceptionHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $eventDispatcher;

    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
    }

    /**
     * @param string $identifier
     * @param Client $client
     */
    private function mockDoctrine(string $identifier, Client $client): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $identifier])
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(Client::class)
            ->willReturn($repository);
    }

    public function testHandleOnNonPasswordGrant()
    {
        $request = (new ServerRequest('GET', ''))->withParsedBody(['grant_type' => 'client']);
        $this->eventDispatcher->expects(self::never())
            ->method('dispatch');

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, null);
        $handler->handle($request, OAuthServerException::accessDenied());
    }

    public function testHandleWithPreviousAuthenticationException()
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

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new AuthenticationFailureEvent($token, $expectedException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, null);
        $handler->handle($request, $exception);
    }

    public function testHandleWithOAuthBadCredentialsException()
    {
        $tokenParameters = ['grant_type' => 'password', 'username' => 'testUser'];
        $request = (new ServerRequest('GET', ''))->withParsedBody($tokenParameters);

        $token = new FailedUserOAuth2Token('testUser');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');
        $expectedException = new BadCredentialsException('test', 10, $exception);
        $expectedException->setToken($token);

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new AuthenticationFailureEvent($token, $expectedException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, null);
        $handler->handle($request, $exception);
    }

    public function testHandleWithNonOAuthBadCredentialsException()
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

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new AuthenticationFailureEvent($token, $expectedException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, null);
        $handler->handle($request, $exception);
    }

    public function testHandleWithOAuthBadCredentialsExceptionAndFronendRequest()
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with Frontend bundle');
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

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new AuthenticationFailureEvent($token, $expectedException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );

        $this->mockDoctrine('test_client', $client);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateFrontendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, $frontendHelper);
        $handler->handle($request, $exception);
    }

    public function testHandleWithOAuthBadCredentialsExceptionAndBackendRequest()
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with Frontend bundle');
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

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new AuthenticationFailureEvent($token, $expectedException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );

        $this->mockDoctrine('test_client', $client);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, $frontendHelper);
        $handler->handle($request, $exception);
    }

    public function testHandleShouldRestoreFrontendHelperStateOnExceptionDuringEventDispatching()
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with Frontend bundle');
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

        $this->mockDoctrine('test_client', $client);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');

        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, $frontendHelper);
        $handler->handle($request, $exception);
    }

    public function testWithWrongClientId()
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with Frontend bundle');
        }

        $tokenParameters = ['grant_type' => 'password', 'username' => 'user', 'client_id' => 'test_client'];
        $request = new ServerRequest([], [], null, null, 'php://input', [], [], [], $tokenParameters);

        $token = new FailedUserOAuth2Token('user');
        $token->setAttributes($tokenParameters);

        $exception = new OAuthServerException('test', 10, 'invalid_credentials');
        $expectedException = new BadCredentialsException('test', 10, $exception);
        $expectedException->setToken($token);

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                new AuthenticationFailureEvent($token, $expectedException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'test_client'])
            ->willReturn(null);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(Client::class)
            ->willReturn($repository);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = new PasswordGrantExceptionHandler($this->eventDispatcher, $this->doctrine, $frontendHelper);
        $handler->handle($request, $exception);
    }
}
