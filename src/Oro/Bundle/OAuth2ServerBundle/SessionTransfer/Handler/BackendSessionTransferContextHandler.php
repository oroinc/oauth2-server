<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler;

use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Security\UserLoaderInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * Creates a backend session from a redeemed Session Transfer Token.
 */
final class BackendSessionTransferContextHandler extends AbstractSessionTransferContextHandler
{
    public function __construct(
        private readonly UserLoaderInterface $userLoader,
        TokenStorageInterface $tokenStorage,
        FirewallMap $firewallMap,
        SessionAuthenticationStrategyInterface $sessionStrategy,
        UserCheckerInterface $userChecker,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($tokenStorage, $firewallMap, $sessionStrategy, $userChecker, $eventDispatcher);
    }

    #[\Override]
    public function supports(SessionTransferToken $transferToken): bool
    {
        return !$transferToken->getClient()->isFrontend();
    }

    #[\Override]
    public function createSession(Request $request, SessionTransferToken $transferToken): void
    {
        $user = $this->userLoader->loadUserByIdentifier($transferToken->getUserIdentifier());

        if (!$user instanceof User) {
            throw new UnauthorizedHttpException(
                'SessionTransfer',
                'The back-office user cannot be found.'
            );
        }

        $organization = $transferToken->getOrganization();

        if (null === $organization) {
            throw new GoneHttpException('The Session Transfer Token does not have an organization.');
        }

        if (!$organization->isEnabled()) {
            throw new GoneHttpException('The Session Transfer Token organization is disabled.');
        }

        if (!$user->hasOrganization($organization)) {
            throw new UnauthorizedHttpException(
                'SessionTransfer',
                'The back-office user does not belong to the Session Transfer Token organization.'
            );
        }

        $this->checkUser($user);

        $securityToken = new SessionTransferAuthenticationToken(
            $user,
            $this->getFirewallName($request),
            $organization,
            $user->getRoles()
        );
        $this->storeSecurityToken($request, $securityToken, true);
    }
}
