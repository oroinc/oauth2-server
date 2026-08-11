<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\SessionTransfer\Handler;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\CustomerBundle\Security\AnonymousCustomerUserRolesProvider;
use Oro\Bundle\CustomerBundle\Security\Firewall\CustomerVisitorCookieFactory;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserTokenFactoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler\FrontendSessionTransferContextHandler;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Oro\Component\Testing\ReflectionUtil;
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

class FrontendSessionTransferContextHandlerTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!\class_exists('Oro\\Bundle\\CustomerBundle\\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
    }

    public function testSupports(): void
    {
        $handler = $this->createHandler();
        $client = new Client();
        $transferToken = (new SessionTransferToken())->setClient($client);

        self::assertFalse($handler->supports($transferToken));

        $client->setFrontend(true);
        self::assertTrue($handler->supports($transferToken));
    }

    public function testCreateCustomerUserSession(): void
    {
        $request = Request::create('/oauth2/session-transfer/consume');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $organization = new Organization();
        ReflectionUtil::setId($organization, 10);
        $organization->setEnabled(true);
        $website = (new Website())->setOrganization($organization);
        ReflectionUtil::setId($website, 20);
        $customerUser = $this->createMock(CustomerUser::class);
        $customerUser->method('getOrganization')->willReturn($organization);
        $customerUser->method('getRoles')->willReturn(['ROLE_FRONTEND_USER']);
        $client = new Client();
        $client->setFrontend(true);
        $transferToken = (new SessionTransferToken())
            ->setClient($client)
            ->setUserIdentifier('customer@example.com')
            ->setOrganization($organization)
            ->setContextData(['website_id' => 20]);
        $userLoader = $this->createMock(UserLoaderInterface::class);
        $userLoader->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('customer@example.com')
            ->willReturn($customerUser);
        $websiteManager = $this->createMock(WebsiteManager::class);
        $websiteManager->expects(self::once())->method('getCurrentWebsite')->willReturn($website);
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->expects(self::once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(new FirewallConfig('frontend', 'user_checker'));
        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects(self::once())->method('checkPreAuth')->with($customerUser);
        $userChecker->expects(self::once())->method('checkPostAuth')->with($customerUser);
        $sessionStrategy = $this->createMock(SessionAuthenticationStrategyInterface::class);
        $sessionStrategy->expects(self::once())
            ->method('onAuthentication')
            ->with($request, self::isInstanceOf(SessionTransferAuthenticationToken::class));
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::isInstanceOf(SessionTransferAuthenticationToken::class));
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InteractiveLoginEvent::class), SecurityEvents::INTERACTIVE_LOGIN);
        $handler = $this->createHandler(
            $userLoader,
            $websiteManager,
            $tokenStorage,
            $firewallMap,
            $sessionStrategy,
            $userChecker,
            $eventDispatcher
        );

        $handler->createSession($request, $transferToken);
    }

    public function testCreateSessionRejectsAnotherWebsite(): void
    {
        $organization = new Organization();
        ReflectionUtil::setId($organization, 10);
        $website = (new Website())->setOrganization($organization);
        ReflectionUtil::setId($website, 21);
        $websiteManager = $this->createMock(WebsiteManager::class);
        $websiteManager->method('getCurrentWebsite')->willReturn($website);
        $transferToken = (new SessionTransferToken())
            ->setClient(new Client())
            ->setOrganization($organization)
            ->setContextData(['website_id' => 20]);
        $handler = $this->createHandler(websiteManager: $websiteManager);

        $this->expectException(GoneHttpException::class);
        $this->expectExceptionMessage('belongs to another website');

        $handler->createSession(new Request(), $transferToken);
    }

    private function createHandler(
        ?UserLoaderInterface $userLoader = null,
        ?WebsiteManager $websiteManager = null,
        ?TokenStorageInterface $tokenStorage = null,
        ?FirewallMap $firewallMap = null,
        ?SessionAuthenticationStrategyInterface $sessionStrategy = null,
        ?UserCheckerInterface $userChecker = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ): FrontendSessionTransferContextHandler {
        return new FrontendSessionTransferContextHandler(
            $userLoader ?? $this->createMock(UserLoaderInterface::class),
            $websiteManager ?? $this->createMock(WebsiteManager::class),
            $this->createMock(CustomerVisitorManager::class),
            $this->createMock(AnonymousCustomerUserTokenFactoryInterface::class),
            $this->createMock(AnonymousCustomerUserRolesProvider::class),
            $this->createMock(CustomerVisitorCookieFactory::class),
            $tokenStorage ?? $this->createMock(TokenStorageInterface::class),
            $firewallMap ?? $this->createMock(FirewallMap::class),
            $sessionStrategy ?? $this->createMock(SessionAuthenticationStrategyInterface::class),
            $userChecker ?? $this->createMock(UserCheckerInterface::class),
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class)
        );
    }
}
