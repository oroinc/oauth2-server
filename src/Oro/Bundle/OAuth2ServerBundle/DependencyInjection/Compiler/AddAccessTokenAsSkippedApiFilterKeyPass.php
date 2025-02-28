<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds `access_token` keyword to the list of skipped API filter keys.
 */
class AddAccessTokenAsSkippedApiFilterKeyPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('oro_api.rest.filter_value_accessor_factory')
            ->addMethodCall('addSkippedFilterKey', ['access_token']);
    }
}
