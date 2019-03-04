<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OroOAuth2ServerExtension extends Extension
{
    public const ALIAS = 'oro_oauth2_server';

    private const SUPPORTED_CLIENT_OWNERS_PARAM = 'oro_oauth2_server.supported_client_owners';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $this->configureSupportedClientOwners($container);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return self::ALIAS;
    }

    /**
     * @param ContainerBuilder $container
     */
    private function configureSupportedClientOwners(ContainerBuilder $container): void
    {
        // add CustomerUser here to avoid creation of a bridge for the customer-portal package
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $container->setParameter(
                self::SUPPORTED_CLIENT_OWNERS_PARAM,
                array_merge(
                    $container->getParameter(self::SUPPORTED_CLIENT_OWNERS_PARAM),
                    ['Oro\Bundle\CustomerBundle\Entity\CustomerUser']
                )
            );
        }
    }
}
