<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Knp\Menu\ItemInterface;
use Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds storefront OAuth applications menu item if customer portal is installed and storefront api is enabled.
 */
class NavigationListener
{
    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var TokenAccessorInterface */
    private $tokenAccessor;

    /** @var ApiFeatureChecker */
    private $featureChecker;

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenAccessorInterface        $tokenAccessor
     * @param ApiFeatureChecker             $featureChecker
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenAccessorInterface $tokenAccessor,
        ApiFeatureChecker $featureChecker
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenAccessor = $tokenAccessor;
        $this->featureChecker = $featureChecker;
    }

    /**
     * @param ConfigureMenuEvent $event
     */
    public function onNavigationConfigure(ConfigureMenuEvent $event)
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')
            || !$this->featureChecker->isFrontendApiEnabled()
        ) {
            return;
        }

        /** @var ItemInterface $systemTabMenuItem */
        $systemTabMenuItem = $event->getMenu()->getChild('system_tab');
        if (!$systemTabMenuItem) {
            return;
        }

        if (!$this->tokenAccessor->hasUser()) {
            return;
        }

        if (!$this->authorizationChecker->isGranted('oro_oauth2_view')) {
            return;
        }

        $systemTabMenuItem->addChild(
            'storefront_oauth_applications',
            [
                'label'           => 'oro.oauth2server.menu.frontend_oauth_application.label',
                'route'           => 'oro_oauth2_storefront_index',
                'linkAttributes'  => ['class' => 'no-hash'],
                'extras'          => [
                    'position' => 21, // just after the User management.
                    'routes'   => [
                        'oro_oauth2_storefront_view',
                        'oro_oauth2_storefront_create',
                        'oro_oauth2_storefront_update'
                    ]
                ],
            ]
        );
    }
}
