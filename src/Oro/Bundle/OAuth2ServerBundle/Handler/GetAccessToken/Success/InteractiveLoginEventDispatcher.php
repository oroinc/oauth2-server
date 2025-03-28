<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Provides a way to dispatch {@see InteractiveLoginEvent} by grant success handlers.
 */
class InteractiveLoginEventDispatcher
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ClientManager $clientManager,
        private TokenStorageInterface $tokenStorage,
        private UserLoaderInterface $backendUserLoader,
        private ?UserLoaderInterface $frontendUserLoader,
        private ?FrontendHelper $frontendHelper
    ) {
    }

    public function dispatch(Request $request, string $clientId, string $userIdentifier): void
    {
        $client = $this->clientManager->getClient($clientId);
        if (null === $client) {
            return;
        }

        $user = $client->isFrontend()
            ? $this->frontendUserLoader->loadUser($userIdentifier)
            : $this->backendUserLoader->loadUser($userIdentifier);
        $token = new OAuth2Token($user, $client->getOrganization());
        $oldToken = $this->tokenStorage->getToken();
        if ($client->isFrontend()) {
            $this->frontendHelper?->emulateFrontendRequest();
        } else {
            $this->frontendHelper?->emulateBackendRequest();
        }
        try {
            $this->tokenStorage->setToken($token);
            $this->eventDispatcher->dispatch(
                new InteractiveLoginEvent($request, $token),
                SecurityEvents::INTERACTIVE_LOGIN
            );
        } finally {
            $this->frontendHelper?->resetRequestEmulation();
            $this->tokenStorage->setToken($oldToken);
        }
    }
}
