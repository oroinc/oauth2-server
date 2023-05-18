<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler\RedirectListenerPass;
use Oro\Bundle\OAuth2ServerBundle\EventListener\RedirectListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RedirectListenerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $container->register('oro_website.event_listener.redirect')
            ->setClass('DefaultListener')
            ->setArguments(['some argument'])
            ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onRequest']);

        $pass = new RedirectListenerPass();
        $pass->process($container);

        $definition = $container->getDefinition('oro_website.event_listener.redirect');
        self::assertEquals(RedirectListener::class, $definition->getClass());
        self::assertEquals(
            [
                new Definition('DefaultListener', ['some argument']),
                new Reference('oro_redirect.routing.router')
            ],
            $definition->getArguments()
        );
    }
}
