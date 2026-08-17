<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token;

use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserTokenFactoryInterface;
use Oro\Bundle\CustomerBundle\Security\Token\ApiAnonymousCustomerUserToken;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates a Session Transfer token for a customer visitor whose session was established by Session Transfer.
 */
final class SessionTransferCustomerVisitorTokenFactory implements AnonymousCustomerUserTokenFactoryInterface
{
    public const string SESSION_KEY = '_oro_oauth2_session_transfer_customer_visitor';

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
        if ($this->isSessionTransferCustomerVisitor($customerVisitor)) {
            return new SessionTransferCustomerVisitorAuthenticationToken(
                $customerVisitor,
                $roles,
                $organization
            );
        }

        return $this->innerFactory->create($customerVisitor, $organization, $roles);
    }

    #[\Override]
    public function createApi(
        CustomerVisitor $customerVisitor,
        Organization $organization,
        array $roles = []
    ): ApiAnonymousCustomerUserToken {
        return $this->innerFactory->createApi($customerVisitor, $organization, $roles);
    }

    private function isSessionTransferCustomerVisitor(CustomerVisitor $customerVisitor): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return false;
        }

        $sessionVisitorId = $request->getSession()->get(self::SESSION_KEY);
        $visitorSessionId = $customerVisitor->getSessionId();

        return \is_string($sessionVisitorId)
            && \is_string($visitorSessionId)
            && \hash_equals($sessionVisitorId, $visitorSessionId);
    }
}
