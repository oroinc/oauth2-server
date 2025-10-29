<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler;

use Oro\Bundle\ApiBundle\Util\DependencyInjectionUtil;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Injects registered API views to the API doc view provider service.
 */
class ApiDocViewProviderCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('oro_oauth2_server.provider.api_doc_view_provider')
            ->setArgument('$views', $this->getApiDocViews($container));
    }

    private function getApiDocViews(ContainerBuilder $container): array
    {
        $views = [];
        $config = DependencyInjectionUtil::getConfig($container);
        foreach ($config['api_doc_views'] as $name => $view) {
            $views[$name] = [$view['label'] ?? null, $view['request_type'] ?? null];
        }

        return $views;
    }
}
