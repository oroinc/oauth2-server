<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\GetAccessToken\Success;

use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success\InteractiveLoginEventDispatcher;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Security\UserLoader;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class InteractiveLoginEventDispatcherTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ClientManager&MockObject $clientManager;
    private TokenStorageInterface&MockObject $tokenStorage;
    private UserLoader&MockObject $backendUserLoader;

    #[\Override]
    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->backendUserLoader = $this->createMock(UserLoader::class);
    }

    private function getDispatcher(
        ?UserLoaderInterface $frontendUserLoader,
        ?FrontendHelper $frontendHelper
    ): InteractiveLoginEventDispatcher {
        return new InteractiveLoginEventDispatcher(
            $this->eventDispatcher,
            $this->clientManager,
            $this->tokenStorage,
            $this->backendUserLoader,
            $frontendUserLoader,
            $frontendHelper
        );
    }

    private function getClient(string $identifier, Organization $organization, bool $isFrontend): Client
    {
        $client = new Client();
        $client->setIdentifier($identifier);
        $client->setOrganization($organization);
        $client->setFrontend($isFrontend);

        return $client;
    }

    public function testDispatchForNotExistingClient(): void
    {
        $request = $this->createMock(Request::class);
        $clientId = 'test_client';

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientId)
            ->willReturn(null);
        $this->backendUserLoader->expects(self::never())
            ->method('loadUser');
        $this->tokenStorage->expects(self::never())
            ->method('getToken');
        $this->tokenStorage->expects(self::never())
            ->method('setToken');
        $this->eventDispatcher->expects(self::never())
            ->method('dispatch');

        $dispatcher = $this->getDispatcher(null, null);
        $dispatcher->dispatch($request, $clientId, 'test_user');
    }

    public function testDispatchWithoutFrontendBundle(): void
    {
        $request = $this->createMock(Request::class);
        $clientId = 'test_client';
        $userIdentifier = 'test_user';
        $organization = new Organization();
        $user = new User();

        $token = new OAuth2Token($user, $organization);
        $oldToken = null;

        $client = $this->getClient($clientId, $organization, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientId)
            ->willReturn($client);

        $this->backendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($userIdentifier)
            ->willReturn($user);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive([$token], [$oldToken]);
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new InteractiveLoginEvent($request, $token), SecurityEvents::INTERACTIVE_LOGIN);

        $dispatcher = $this->getDispatcher(null, null);
        $dispatcher->dispatch($request, $clientId, $userIdentifier);
    }

    public function testDispatchForFrontendRequest(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $request = $this->createMock(Request::class);
        $clientId = 'test_client';
        $userIdentifier = 'test_user';
        $organization = new Organization();
        $user = $this->createMock(UserInterface::class);

        $token = new OAuth2Token($user, $organization);
        $oldToken = null;

        $client = $this->getClient($clientId, $organization, true);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientId)
            ->willReturn($client);

        $frontendUserLoader = $this->createMock(UserLoaderInterface::class);
        $frontendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($userIdentifier)
            ->willReturn($user);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive([$token], [$oldToken]);
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new InteractiveLoginEvent($request, $token), SecurityEvents::INTERACTIVE_LOGIN);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateFrontendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $dispatcher = $this->getDispatcher($frontendUserLoader, $frontendHelper);
        $dispatcher->dispatch($request, $clientId, $userIdentifier);
    }

    public function testDispatchForBackendRequest(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $request = $this->createMock(Request::class);
        $clientId = 'test_client';
        $userIdentifier = 'test_user';
        $organization = new Organization();
        $user = new User();

        $token = new OAuth2Token($user, $organization);
        $oldToken = null;

        $client = $this->getClient($clientId, $organization, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientId)
            ->willReturn($client);

        $frontendUserLoader = $this->createMock(UserLoaderInterface::class);
        $this->backendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($userIdentifier)
            ->willReturn($user);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive([$token], [$oldToken]);
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new InteractiveLoginEvent($request, $token), SecurityEvents::INTERACTIVE_LOGIN);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $dispatcher = $this->getDispatcher($frontendUserLoader, $frontendHelper);
        $dispatcher->dispatch($request, $clientId, $userIdentifier);
    }

    public function testDispatchOldTokenAndFrontendHelperShouldBeRestoredOnExceptionDuringDispatch(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $exception = new \Exception('some error');
        $this->expectException(get_class($exception));

        $request = $this->createMock(Request::class);
        $clientId = 'test_client';
        $userIdentifier = 'test_user';
        $organization = new Organization();
        $user = new User();

        $token = new OAuth2Token($user, $organization);
        $oldToken = null;

        $client = $this->getClient($clientId, $organization, false);
        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientId)
            ->willReturn($client);

        $this->backendUserLoader->expects(self::once())
            ->method('loadUser')
            ->with($userIdentifier)
            ->willReturn($user);
        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($oldToken);
        $this->tokenStorage->expects(self::exactly(2))
            ->method('setToken')
            ->withConsecutive([$token], [$oldToken]);

        $frontendHelper = $this->createMock(FrontendHelper::class);
        $frontendHelper->expects(self::once())
            ->method('emulateBackendRequest');
        $frontendHelper->expects(self::once())
            ->method('resetRequestEmulation');

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willThrowException($exception);

        $dispatcher = $this->getDispatcher($this->createMock(UserLoaderInterface::class), $frontendHelper);
        $dispatcher->dispatch($request, $clientId, $userIdentifier);
    }
}
