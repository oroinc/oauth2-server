<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Success;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CustomerBundle\Security\CustomerUserLoader;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\UserBundle\Security\UserLoader;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * The handler that dispatches the "security.interactive_login" event in case of
 * getting an OAuth access token successfully processed.
 */
class PasswordGrantSuccessHandler implements SuccessHandlerInterface
{
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var RequestStack */
    private $requestStack;

    /**
     * @var ManagerRegistry
     * @deprecated
     */
    private $doctrine;

    /** @var ClientManager */
    private $clientManager;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var UserLoader */
    private $backendUserLoader;

    /** @var CustomerUserLoader */
    private $frontendUserLoader;

    /** @var FrontendHelper */
    private $frontendHelper;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        RequestStack $requestStack,
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        UserLoader $backendUserLoader,
        ?CustomerUserLoader $frontendUserLoader,
        ?FrontendHelper $frontendHelper
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->doctrine = $doctrine;
        $this->frontendHelper = $frontendHelper;
        $this->backendUserLoader = $backendUserLoader;
        $this->frontendUserLoader = $frontendUserLoader;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @deprecated
     */
    public function setClientManager(ClientManager $clientManager): void
    {
        $this->clientManager = $clientManager;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): void
    {
        $parameters = $request->getParsedBody();
        if ('password' !== $parameters['grant_type']) {
            return;
        }

        /** @var Client $client */
        $client = $this->clientManager->getClient($parameters['client_id']);

        if ($client->isFrontend()) {
            $user = $this->frontendUserLoader->loadUser($parameters['username']);
        } else {
            $user = $this->backendUserLoader->loadUser($parameters['username']);
        }

        $token = new OAuth2Token($user, $client->getOrganization());
        $symfonyRequest = $this->requestStack->getMasterRequest();

        if ($this->frontendHelper) {
            if ($client->isFrontend()) {
                $this->frontendHelper->emulateFrontendRequest();
            } else {
                $this->frontendHelper->emulateBackendRequest();
            }
        }
        try {
            $oldToken = $this->tokenStorage->getToken();
            $this->tokenStorage->setToken($token);
            $this->eventDispatcher->dispatch(
                new InteractiveLoginEvent($symfonyRequest, $token),
                SecurityEvents::INTERACTIVE_LOGIN
            );
        } finally {
            if ($this->frontendHelper) {
                $this->frontendHelper->resetRequestEmulation();
            }
            $this->tokenStorage->setToken($oldToken);
        }
    }
}
