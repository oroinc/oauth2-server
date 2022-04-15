<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds customer user OAuth applications menu item if customer portal is installed and storefront api is enabled.
 */
class NavigationListener
{
    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var TokenAccessorInterface */
    private $tokenAccessor;

    /** @var ApiFeatureChecker */
    private $featureChecker;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenAccessorInterface $tokenAccessor,
        ApiFeatureChecker $featureChecker
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenAccessor = $tokenAccessor;
        $this->featureChecker = $featureChecker;
    }

    public function onNavigationConfigure(ConfigureMenuEvent $event)
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')
            || !$this->featureChecker->isFrontendApiEnabled()
        ) {
            return;
        }

        $customersTabMenuItem = $event->getMenu()->getChild('customers_tab');
        if (!$customersTabMenuItem) {
            return;
        }

        if (!$this->tokenAccessor->hasUser()) {
            return;
        }

        if (!$this->authorizationChecker->isGranted('oro_oauth2_view')) {
            return;
        }

        $customersTabMenuItem->addChild(
            'frontend_oauth_applications',
            [
                'label'           => 'oro.oauth2server.menu.frontend_oauth_application.label',
                'route'           => 'oro_oauth2_frontend_index',
                'linkAttributes'  => ['class' => 'no-hash'],
                'extras'          => [
                    'routes'   => [
                        'oro_oauth2_frontend_view',
                        'oro_oauth2_frontend_create',
                        'oro_oauth2_frontend_update'
                    ]
                ],
            ]
        );
    }
}
