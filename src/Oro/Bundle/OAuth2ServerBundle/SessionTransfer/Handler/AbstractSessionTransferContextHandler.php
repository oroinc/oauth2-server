<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler;

use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * Provides common functions for context handlers.
 */
abstract class AbstractSessionTransferContextHandler implements SessionTransferContextHandlerInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly FirewallMap $firewallMap,
        private readonly SessionAuthenticationStrategyInterface $sessionStrategy,
        private readonly UserCheckerInterface $userChecker,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    protected function getFirewallName(Request $request): string
    {
        $firewallConfig = $this->firewallMap->getFirewallConfig($request);

        if (null === $firewallConfig) {
            throw new ServiceUnavailableHttpException(null, 'The Session Transfer route is not handled by a firewall.');
        }

        if (!$firewallConfig->isSecurityEnabled()) {
            throw new ServiceUnavailableHttpException(null, 'Security is disabled for the Session Transfer route.');
        }

        if ($firewallConfig->isStateless()) {
            throw new ServiceUnavailableHttpException(
                null,
                'The Session Transfer route must be handled by a stateful firewall.'
            );
        }

        return $firewallConfig->getName();
    }

    protected function checkUser(UserInterface $user): void
    {
        try {
            $this->userChecker->checkPreAuth($user);
            $this->userChecker->checkPostAuth($user);
        } catch (AuthenticationException $exception) {
            throw new UnauthorizedHttpException(
                'SessionTransfer',
                'The Session Transfer subject cannot be authenticated.',
                $exception
            );
        }
    }

    protected function storeSecurityToken(
        Request $request,
        TokenInterface $securityToken,
        bool $dispatchInteractiveLogin
    ): void {
        if (!$request->hasSession()) {
            throw new ServiceUnavailableHttpException(
                null,
                'A session is not available for the Session Transfer route.'
            );
        }

        $this->sessionStrategy->onAuthentication($request, $securityToken);
        $this->tokenStorage->setToken($securityToken);
        if (!$dispatchInteractiveLogin) {
            return;
        }

        $event = new InteractiveLoginEvent($request, $securityToken);
        $this->eventDispatcher->dispatch($event, SecurityEvents::INTERACTIVE_LOGIN);
    }
}
