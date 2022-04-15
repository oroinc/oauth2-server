<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\ClientCredentialsGrantSuccessHandler;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoginAttemptLogger;
use Psr\Http\Message\ServerRequestInterface;

class ClientCredentialsGrantSuccessHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrine;

    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var UserLoginAttemptLogger|\PHPUnit\Framework\MockObject\MockObject */
    private UserLoginAttemptLogger $backendLogger;

    /** @var UserLoginAttemptLogger|\PHPUnit\Framework\MockObject\MockObject */
    private $frontendLogger;

    /** @var ObjectRepository|\PHPUnit\Framework\MockObject\MockObject  */
    private $repo;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->backendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $this->frontendLogger = $this->createMock(UserLoginAttemptLogger::class);
        $this->repo = $this->createMock(ObjectRepository::class);
    }

    public function testHandleWithNonClientCredentialsRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'password']);

        $this->backendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $this->frontendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $handler = new ClientCredentialsGrantSuccessHandler(
            $this->doctrine,
            $this->clientManager,
            $this->backendLogger,
            $this->frontendLogger
        );
        $handler->handle($request);
    }

    public function testHandleWithBackendRequest(): void
    {
        $user = new User();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'client_credentials', 'client_id' => 10]);

        $client = new Client();
        $client->setFrontend(false);
        $client->setOwnerEntity('test_backend', 1);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with(10)
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('test_backend')
            ->willReturn($this->repo);

        $this->repo->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->backendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');
        $this->frontendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');

        $handler = new ClientCredentialsGrantSuccessHandler(
            $this->doctrine,
            $this->clientManager,
            $this->backendLogger,
            $this->frontendLogger
        );
        $handler->handle($request);
    }

    public function testHandleWithFrontendRequest(): void
    {
        $user = new User();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['grant_type' => 'client_credentials', 'client_id' => 20]);

        $client = new Client();
        $client->setFrontend(true);
        $client->setOwnerEntity('test_backend', 2);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with(20)
            ->willReturn($client);

        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with('test_backend')
            ->willReturn($this->repo);

        $this->repo->expects(self::once())
            ->method('find')
            ->with(2)
            ->willReturn($user);


        $this->backendLogger->expects(self::never())
            ->method('logSuccessLoginAttempt');
        $this->frontendLogger->expects(self::once())
            ->method('logSuccessLoginAttempt')
            ->with($user, 'OAuth');

        $handler = new ClientCredentialsGrantSuccessHandler(
            $this->doctrine,
            $this->clientManager,
            $this->backendLogger,
            $this->frontendLogger
        );
        $handler->handle($request);
    }
}
