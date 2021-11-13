<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use GuzzleHttp\Psr7\ServerRequest;
use Oro\Bundle\CustomerBundle\Security\CustomerUserLoader;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\PasswordGrantSuccessHandler;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class PasswordGrantSuccessHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $eventDispatcher;

    /** @var RequestStack|\PHPUnit\Framework\MockObject\MockObject */
    private $requestStack;

    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var TokenStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenStorage;

    /** @var UserLoader|\PHPUnit\Framework\MockObject\MockObject */
    private $backendUserLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->backendUserLoader = $this->createMock(UserLoader::class);
    }

    private function getHandler(
        CustomerUserLoader $frontendUserLoader = null,
        FrontendHelper $frontendHelper = null
    ): PasswordGrantSuccessHandler {
        return new PasswordGrantSuccessHandler(
            $this->eventDispatcher,
            $this->requestStack,
            $this->clientManager,
            $this->tokenStorage,
            $this->backendUserLoader,
            $frontendUserLoader,
            $frontendHelper
        );
    }

    public function testHandleOnNonPasswordGrant(): void
    {
        $request = (new ServerRequest('GET', ''))->withParsedBody(['grant_type' => 'client']);

        $this->eventDispatcher->expects(self::never())
            ->method('dispatch');

        $handler = $this->getHandler();
        $handler->handle($request);
    }

    public function testHandle(): void
    {
        $requestParameters = [
            'grant_type' => 'password',
            'client_id'  => 'test',
            'username'   => 'testUser',
        ];
        $request = (new ServerRequest('GET', ''))->withParsedBody($requestParameters);

        $organization = new Organization();
        $user = new User();
        $symfonyRequest = $this->createMock(Request::class);

        $expectedToken = new OAuth2Token($user, $organization);
        $oldToken = null;

        $this->mockClient('test', $organization);
        $this->backendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with('testUser')
            ->willReturn($user);
        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive(
                [$expectedToken],
                [$oldToken]
            );
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new InteractiveLoginEvent($symfonyRequest, $expectedToken), SecurityEvents::INTERACTIVE_LOGIN);

        $handler = $this->getHandler();
        $handler->handle($request);
    }

    private function mockClient(string $identifier, Organization $organization, bool $isFrontend = false): void
    {
        $client = new Client();
        $client->setFrontend($isFrontend);
        $client->setOrganization($organization);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($identifier)
            ->willReturn($client);
    }

    public function testHandleOnFrontendRequest(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with FrontendBundle');
        }

        $requestParameters = [
            'grant_type' => 'password',
            'client_id'  => 'test',
            'username'   => 'testUser',
        ];
        $request = (new ServerRequest('GET', ''))->withParsedBody($requestParameters);

        $organization = new Organization();
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserRoles')->willReturn([]);
        $symfonyRequest = $this->createMock(Request::class);

        $expectedToken = new OAuth2Token($user, $organization);
        $oldToken = null;

        $this->mockClient('test', $organization, true);
        $frontendUserLoader = $this->createMock(CustomerUserLoader::class);
        $frontendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with('testUser')
            ->willReturn($user);
        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive(
                [$expectedToken],
                [$oldToken]
            );
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new InteractiveLoginEvent($symfonyRequest, $expectedToken), SecurityEvents::INTERACTIVE_LOGIN);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateFrontendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = $this->getHandler($frontendUserLoader, $frontendHelper);
        $handler->handle($request);
    }

    public function testHandleOnBackendRequest(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with FrontendBundle');
        }

        $requestParameters = [
            'grant_type' => 'password',
            'client_id'  => 'test',
            'username'   => 'testUser',
        ];
        $request = (new ServerRequest('GET', ''))->withParsedBody($requestParameters);

        $organization = new Organization();
        $user = new User();
        $symfonyRequest = $this->createMock(Request::class);

        $expectedToken = new OAuth2Token($user, $organization);
        $oldToken = null;

        $this->mockClient('test', $organization, false);
        $frontendUserLoader = $this->createMock(CustomerUserLoader::class);
        $this->backendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with('testUser')
            ->willReturn($user);
        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive(
                [$expectedToken],
                [$oldToken]
            );
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new InteractiveLoginEvent($symfonyRequest, $expectedToken), SecurityEvents::INTERACTIVE_LOGIN);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $handler = $this->getHandler($frontendUserLoader, $frontendHelper);
        $handler->handle($request);
    }

    public function testHandleOldTokenAndFrontendHelperShouldBeRestoredOnExceptionDuringDispatch(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\Request\FrontendHelper')) {
            self::markTestSkipped('Could be tested only with FrontendBundle');
        }

        $exception = new \Exception('some error');
        $this->expectException(get_class($exception));

        $requestParameters = [
            'grant_type' => 'password',
            'client_id'  => 'test',
            'username'   => 'testUser',
        ];
        $request = (new ServerRequest('GET', ''))->withParsedBody($requestParameters);

        $organization = new Organization();
        $user = new User();
        $symfonyRequest = $this->createMock(Request::class);

        $expectedToken = new OAuth2Token($user, $organization);
        $oldToken = null;

        $this->mockClient('test', $organization);
        $this->backendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with('testUser')
            ->willReturn($user);
        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($symfonyRequest);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive(
                [$expectedToken],
                [$oldToken]
            );

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willThrowException($exception);

        $handler = $this->getHandler($this->createMock(CustomerUserLoader::class), $frontendHelper);
        $handler->handle($request);
    }
}
