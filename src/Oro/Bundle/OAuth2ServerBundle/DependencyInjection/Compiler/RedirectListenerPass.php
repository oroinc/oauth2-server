<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler;

use Oro\Bundle\OAuth2ServerBundle\EventListener\RedirectListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces the default redirect listener with the listener that turns off redirect
 * in case of a OAuth2 token authorization is in use.
 */
class RedirectListenerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('oro_website.event_listener.redirect')) {
            $baseRedirectListenerDef = clone $container->getDefinition('oro_website.event_listener.redirect');
            $baseRedirectListenerDef->clearTags();
            $container->getDefinition('oro_website.event_listener.redirect')
                ->setClass(RedirectListener::class)
                ->setArguments([
                    $baseRedirectListenerDef,
                    new Reference('oro_redirect.routing.router')
                ]);
        }
    }
}
