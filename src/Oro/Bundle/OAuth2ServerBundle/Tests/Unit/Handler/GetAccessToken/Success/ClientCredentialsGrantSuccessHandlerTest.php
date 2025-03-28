<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use GuzzleHttp\Psr7\ServerRequest;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\ClientCredentialsGrantSuccessHandler;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientCredentialsGrantSuccessHandlerTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private ClientManager&MockObject $clientManager;
    private UserLoginAttemptLogger&MockObject $backendLogger;
    private ObjectRepository&MockObject $repo;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->backendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $this->repo = $this->createMock(ObjectRepository::class);
    }

    private function getHandler(?UserLoginAttemptLogger $frontendLogger): ClientCredentialsGrantSuccessHandler
    {
        return new ClientCredentialsGrantSuccessHandler(
            $this->doctrine,
            $this->clientManager,
            $this->backendLogger,
            $frontendLogger
        );
    }

    private function getClient(string $ownerEntityClass, int $ownerEntityId, bool $isFrontend): Client
    {
        $client = new Client();
        $client->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        $client->setFrontend($isFrontend);

        return $client;
    }

    public function testHandleWithNonClientCredentialsRequest(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'other']);

        $this->backendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }

    public function testHandleWithoutFrontendBundle(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials', 'client_id' => 'test_client']);
        $user = new User();

        $client = $this->getClient('Test\User', 1, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');

        $handler = $this->getHandler(null);
        $handler->handle($request);
    }

    public function testHandleWithBackendRequest(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials', 'client_id' => 'test_client']);
        $user = new User();

        $client = $this->getClient('Test\User', 1, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }

    public function testHandleWithBackendRequestWhenCredentialsProvidedInBasicAuthorizationHeader(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials'])
            ->withHeader('Authorization', ['Basic ' . base64_encode('test_client:test_secret')]);
        $user = new User();

        $client = $this->getClient('Test\User', 1, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }

    public function testHandleWithBackendRequestWhenCredentialsProvidedInBasicAuthorizationHeaderAndRequestBody(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials', 'client_id' => 'test_client'])
            ->withHeader('Authorization', ['Basic ' . base64_encode('test_client:test_secret')]);
        $user = new User();

        $client = $this->getClient('Test\User', 1, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }

    public function testHandleWithFrontendRequest(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials', 'client_id' => 'test_client']);
        $user = new User();

        $client = $this->getClient('Test\User', 1, true);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }

    public function testHandleWithFrontendRequestWhenCredentialsProvidedInBasicAuthorizationHeader(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials'])
            ->withHeader('Authorization', ['Basic ' . base64_encode('test_client:test_secret')]);
        $user = new User();

        $client = $this->getClient('Test\User', 1, true);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }

    public function testHandleWithFrontendRequestWhenCredentialsProvidedInBasicAuthorizationHeaderAndRequestBody(): void
    {
        $request = (new ServerRequest('GET', ''))
            ->withParsedBody(['grant_type' => 'client_credentials', 'client_id' => 'test_client'])
            ->withHeader('Authorization', ['Basic ' . base64_encode('test_client:test_secret')]);
        $user = new User();

        $client = $this->getClient('Test\User', 1, true);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with('test_client')
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('Test\User')
            ->willReturn($this->repo);
        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $frontendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');

        $handler = $this->getHandler($frontendLogger);
        $handler->handle($request);
    }
}
