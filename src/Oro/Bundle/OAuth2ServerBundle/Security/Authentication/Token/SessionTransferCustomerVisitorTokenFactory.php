<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserTokenFactoryInterface;
use Oro\Bundle\CustomerBundle\Security\Token\ApiAnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Provider\CurrentClientProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Creates a Session Transfer token for a customer visitor whose session was established by Session Transfer.
 */
final class SessionTransferCustomerVisitorTokenFactory implements AnonymousCustomerUserTokenFactoryInterface
{
    /**
     * The session attribute that holds the session id of the customer visitor transferred via Session Transfer.
     */
    public const string SESSION_KEY = '_oro_oauth2_session_transfer_customer_visitor';

    /**
     * The session attribute that holds the database id of the OAuth2 client
     * that transferred the customer visitor via Session Transfer.
     */
    public const string CLIENT_SESSION_KEY = '_oro_oauth2_session_transfer_customer_visitor_client';

    public function __construct(
        private readonly AnonymousCustomerUserTokenFactoryInterface $innerFactory,
        private readonly RequestStack $requestStack
    ) {
    }

    #[\Override]
    public function create(
        CustomerVisitor $customerVisitor,
        Organization $organization,
        array $roles = []
    ): AnonymousCustomerUserToken {
        $request = $this->requestStack->getCurrentRequest();
        $session = null !== $request && $request->hasSession() ? $request->getSession() : null;
        if (null === $session || !$this->isSessionTransferCustomerVisitor($session, $customerVisitor)) {
            return $this->innerFactory->create($customerVisitor, $organization, $roles);
        }

        $token = new SessionTransferCustomerVisitorAuthenticationToken(
            $customerVisitor,
            $roles,
            $organization
        );
        $clientId = $session->get(self::CLIENT_SESSION_KEY);
        if (\is_int($clientId)) {
            $token->setAttribute(CurrentClientProvider::CLIENT_ID_ATTRIBUTE, $clientId);
        }

        return $token;
    }

    #[\Override]
    public function createApi(
        CustomerVisitor $customerVisitor,
        Organization $organization,
        array $roles = []
    ): ApiAnonymousCustomerUserToken {
        return $this->innerFactory->createApi($customerVisitor, $organization, $roles);
    }

    private function isSessionTransferCustomerVisitor(
        SessionInterface $session,
        CustomerVisitor $customerVisitor
    ): bool {
        $sessionVisitorId = $session->get(self::SESSION_KEY);
        $visitorSessionId = $customerVisitor->getSessionId();

        return \is_string($sessionVisitorId)
            && \is_string($visitorSessionId)
            && \hash_equals($sessionVisitorId, $visitorSessionId);
    }
}
