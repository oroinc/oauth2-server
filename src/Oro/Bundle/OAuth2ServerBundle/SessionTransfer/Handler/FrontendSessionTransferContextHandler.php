<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\CustomerBundle\Security\AnonymousCustomerUserAuthenticator;
use Oro\Bundle\CustomerBundle\Security\AnonymousCustomerUserRolesProvider;
use Oro\Bundle\CustomerBundle\Security\Firewall\CustomerVisitorCookieFactory;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorTokenFactory;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * Creates Customer User or Customer Visitor storefront sessions from Session Transfer Tokens.
 */
final class FrontendSessionTransferContextHandler extends AbstractSessionTransferContextHandler
{
    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly UserLoaderInterface $userLoader,
        private readonly WebsiteManager $websiteManager,
        private readonly CustomerVisitorManager $visitorManager,
        private readonly AnonymousCustomerUserRolesProvider $anonymousRolesProvider,
        private readonly CustomerVisitorCookieFactory $cookieFactory,
        TokenStorageInterface $tokenStorage,
        FirewallMap $firewallMap,
        SessionAuthenticationStrategyInterface $sessionStrategy,
        UserCheckerInterface $userChecker,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct(
            $tokenStorage,
            $firewallMap,
            $sessionStrategy,
            $userChecker,
            $eventDispatcher
        );
    }

    #[\Override]
    public function supports(SessionTransferToken $transferToken): bool
    {
        return $transferToken->getClient()->isFrontend();
    }

    #[\Override]
    public function createSession(
        Request $request,
        SessionTransferToken $transferToken
    ): void {
        $organization = $this->validateWebsiteContext($transferToken);
        $userIdentifier = $transferToken->getUserIdentifier();
        if (VisitorIdentifierUtil::isVisitorIdentifier($userIdentifier)) {
            $this->createVisitorSession($request, $userIdentifier, $organization);

            return;
        }

        $this->createCustomerUserSession($request, $userIdentifier, $organization);
    }

    private function createCustomerUserSession(
        Request $request,
        string $userIdentifier,
        Organization $organization
    ): void {
        $customerUser = $this->userLoader->loadUserByIdentifier($userIdentifier);

        if (!$customerUser instanceof CustomerUser) {
            throw new UnauthorizedHttpException('SessionTransfer', 'The customer user cannot be found.');
        }

        $customerUserOrganization = $customerUser->getOrganization();
        if (
            null === $customerUserOrganization
            || $customerUserOrganization->getId() !== $organization->getId()
        ) {
            throw new UnauthorizedHttpException(
                'SessionTransfer',
                'The customer user does not belong to the Session Transfer Token organization.'
            );
        }

        $this->checkUser($customerUser);

        $securityToken = new SessionTransferAuthenticationToken(
            $customerUser,
            $this->getFirewallName($request),
            $organization,
            $customerUser->getRoles()
        );
        $this->storeSecurityToken($request, $securityToken, true);
    }

    private function createVisitorSession(Request $request, string $userIdentifier, Organization $organization): void
    {
        $visitorSessionId = VisitorIdentifierUtil::decodeIdentifier($userIdentifier);
        $visitor = $this->visitorManager->findOrCreate($visitorSessionId);

        $securityToken = new SessionTransferCustomerVisitorAuthenticationToken(
            $visitor,
            $this->anonymousRolesProvider->getRoles(),
            $organization
        );
        $request->attributes->set(
            AnonymousCustomerUserAuthenticator::COOKIE_ATTR_NAME,
            $this->cookieFactory->getCookie($visitor->getSessionId())
        );
        $request->getSession()->set(
            SessionTransferCustomerVisitorTokenFactory::SESSION_KEY,
            $visitor->getSessionId()
        );
        $this->storeSecurityToken($request, $securityToken, false);
    }

    private function validateWebsiteContext(SessionTransferToken $transferToken): Organization
    {
        $contextData = $transferToken->getContextData();
        $expectedWebsiteId = $contextData['website_id'] ?? null;

        if (
            !\is_int($expectedWebsiteId)
            && !\ctype_digit((string) $expectedWebsiteId)
        ) {
            throw new GoneHttpException('The Session Transfer Token does not have a valid website context.');
        }

        $website = $this->websiteManager->getCurrentWebsite();
        if (null === $website) {
            throw new GoneHttpException('The current website cannot be found.');
        }

        if ((int) $website->getId() !== (int) $expectedWebsiteId) {
            throw new GoneHttpException('The Session Transfer Token belongs to another website.');
        }

        $websiteOrganization = $website->getOrganization();
        if (null === $websiteOrganization) {
            throw new GoneHttpException('The current website is not assigned to an organization.');
        }

        $tokenOrganization = $transferToken->getOrganization();
        if (null === $tokenOrganization) {
            throw new GoneHttpException('The Session Transfer Token does not have an organization.');
        }

        if ($websiteOrganization->getId() !== $tokenOrganization->getId()) {
            throw new GoneHttpException('The Session Transfer Token belongs to another organization.');
        }

        if (!$tokenOrganization->isEnabled()) {
            throw new GoneHttpException('The Session Transfer Token organization is disabled.');
        }

        return $tokenOrganization;
    }
}
