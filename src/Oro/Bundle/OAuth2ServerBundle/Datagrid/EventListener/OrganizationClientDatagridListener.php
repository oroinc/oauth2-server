<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener;

use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

/**
 * The listener that adds filter by organization to OAuth 2.0 clients grid query.
 */
class OrganizationClientDatagridListener
{
    /** @var TokenAccessorInterface */
    private $tokenAccessor;

    /**
     * @param TokenAccessorInterface $tokenAccessor
     */
    public function __construct(TokenAccessorInterface $tokenAccessor)
    {
        $this->tokenAccessor = $tokenAccessor;
    }

    /**
     * @param BuildBefore $event
     */
    public function onBuildBefore(BuildBefore $event): void
    {
        $organization = $this->tokenAccessor->getOrganization();
        if (null === $organization) {
            return;
        }

        $event->getConfig()->getOrmQuery()
            ->addAndWhere(sprintf('IDENTITY(client.organization) = %s', $organization->getId()));
    }
}
