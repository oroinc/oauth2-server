<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\DependencyInjectionUtil;
use Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer\OAuthDescriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services that is used to generate OpenAPI specifications.
 */
class OpenApiCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $views = $this->getApiDocViews($container);
        foreach ($views as $view => $config) {
            $requestType = $config['request_type'];
            if (!$requestType || !\in_array(RequestType::JSON_API, $requestType, true)) {
                // OpenAPI specification generation is implemented for JSON:API only
                continue;
            }
            $container->register('oro_oauth2_server.open_api.describer.' . $view, OAuthDescriber::class)
                ->addArgument(new Reference('router'))
                ->addArgument(new Reference('oro_api.api_doc.open_api.data_type_describe_helper'))
                ->addTag('oro.api.open_api.describer.' . $view);
        }
    }

    private function getApiDocViews(ContainerBuilder $container): array
    {
        $config = DependencyInjectionUtil::getConfig($container);

        return $config['api_doc_views'];
    }
}
