<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Handler;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler\BackendSessionTransferContextHandler;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

class BackendSessionTransferContextHandlerTest extends TestCase
{
    public function testSupports(): void
    {
        $handler = $this->createHandler();
        $client = new Client();
        $transferToken = (new SessionTransferToken())->setClient($client);

        self::assertTrue($handler->supports($transferToken));

        $client->setFrontend(true);
        self::assertFalse($handler->supports($transferToken));
    }

    public function testCreateSession(): void
    {
        $request = Request::create('/oauth2/session-transfer/consume');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $organization = new Organization();
        $organization->setEnabled(true);
        $user = new User();
        $user->setUsername('user@example.com');
        $user->addOrganization($organization);
        $client = new Client();
        $transferToken = (new SessionTransferToken())
            ->setClient($client)
            ->setUserIdentifier('user@example.com')
            ->setOrganization($organization);
        $userLoader = $this->createMock(UserLoaderInterface::class);
        $userLoader->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('user@example.com')
            ->willReturn($user);
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->expects(self::once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(new FirewallConfig('main', 'user_checker'));
        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects(self::once())->method('checkPreAuth')->with($user);
        $userChecker->expects(self::once())->method('checkPostAuth')->with($user);
        $securityToken = null;
        $sessionStrategy = $this->createMock(SessionAuthenticationStrategyInterface::class);
        $sessionStrategy->expects(self::once())
            ->method('onAuthentication')
            ->with(
                $request,
                self::callback(
                    static function (SessionTransferAuthenticationToken $token) use (
                        &$securityToken,
                        $user,
                        $organization
                    ): bool {
                        self::assertSame($user, $token->getUser());
                        self::assertSame($organization, $token->getOrganization());
                        self::assertSame('main', $token->getFirewallName());
                        self::assertSame($user->getRoles(), $token->getRoleNames());
                        $securityToken = $token;

                        return true;
                    }
                )
            );
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(
                self::callback(
                    static function (SessionTransferAuthenticationToken $token) use (&$securityToken): bool {
                        self::assertSame($securityToken, $token);

                        return true;
                    }
                )
            );
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    static function (InteractiveLoginEvent $event) use ($request, &$securityToken): bool {
                        self::assertSame($request, $event->getRequest());
                        self::assertSame($securityToken, $event->getAuthenticationToken());

                        return true;
                    }
                ),
                SecurityEvents::INTERACTIVE_LOGIN
            );
        $handler = new BackendSessionTransferContextHandler(
            $userLoader,
            $tokenStorage,
            $firewallMap,
            $sessionStrategy,
            $userChecker,
            $eventDispatcher
        );

        $handler->createSession($request, $transferToken);
    }

    public function testCreateSessionRejectsMissingOrganization(): void
    {
        $user = new User();
        $userLoader = $this->createMock(UserLoaderInterface::class);
        $userLoader->method('loadUserByIdentifier')->willReturn($user);
        $transferToken = (new SessionTransferToken())
            ->setClient(new Client())
            ->setUserIdentifier('user@example.com');
        $handler = $this->createHandler($userLoader);

        $this->expectException(GoneHttpException::class);
        $this->expectExceptionMessage('does not have an organization');

        $handler->createSession(new Request(), $transferToken);
    }

    private function createHandler(?UserLoaderInterface $userLoader = null): BackendSessionTransferContextHandler
    {
        return new BackendSessionTransferContextHandler(
            $userLoader ?? $this->createMock(UserLoaderInterface::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(FirewallMap::class),
            $this->createMock(SessionAuthenticationStrategyInterface::class),
            $this->createMock(UserCheckerInterface::class),
            $this->createMock(EventDispatcherInterface::class)
        );
    }
}
