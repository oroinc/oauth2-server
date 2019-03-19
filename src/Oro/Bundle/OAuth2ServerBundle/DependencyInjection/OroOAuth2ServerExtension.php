<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use Oro\Component\DependencyInjection\ExtendedContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

class OroOAuth2ServerExtension extends Extension implements PrependExtensionInterface
{
    public const ALIAS = 'oro_oauth2_server';

    private const SUPPORTED_GRANT_TYPES_PARAM   = 'oro_oauth2_server.supported_grant_types';
    private const SUPPORTED_CLIENT_OWNERS_PARAM = 'oro_oauth2_server.supported_client_owners';
    private const CORS_PREFLIGHT_MAX_AGE_PARAM  = 'oro_oauth2_server.cors.preflight_max_age';
    private const CORS_ALLOW_ORIGINS_PARAM      = 'oro_oauth2_server.cors.allow_origins';

    private const PRIVATE_KEY_SERVICE = 'oro_oauth2_server.league.private_key';
    private const PUBLIC_KEY_SERVICE  = 'oro_oauth2_server.league.public_key';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        if ('test' === $container->getParameter('kernel.environment')) {
            $keysFileLocator = new FileLocator(__DIR__ . '/../Tests/Functional/Environment/keys');
            $config['authorization_server']['private_key'] = $keysFileLocator->locate('private.key');
            $config['resource_server']['public_key'] = $keysFileLocator->locate('public.key');
        }

        $this->configureSupportedClientOwners($container);
        $this->configureAuthorizationServer($container, $config['authorization_server']);
        $this->configureResourceServer($container, $config['resource_server']);
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        if ($container instanceof ExtendedContainerBuilder) {
            $this->configureSecurityFirewalls($container);
        }

        if ('test' === $container->getParameter('kernel.environment')) {
            $fileLocator = new FileLocator(__DIR__ . '/../Tests/Functional/Environment');
            $configData = Yaml::parse(file_get_contents($fileLocator->locate('app.yml')));
            foreach ($configData as $name => $config) {
                $container->prependExtensionConfig($name, $config);
            }
        }
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

    /**
     * @param ContainerBuilder $container
     * @param array            $config
     */
    private function configureAuthorizationServer(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(self::PRIVATE_KEY_SERVICE)
            ->setArgument(0, $config['private_key']);

        $privateKey = new Definition(
            CryptKey::class,
            [new Expression(sprintf('service("%s").getKeyPath()', self::PRIVATE_KEY_SERVICE)), null, false]
        );
        $authorizationServer = $container->getDefinition('oro_oauth2_server.league.authorization_server')
            ->setArgument(3, $privateKey)
            ->setArgument(4, $config['encryption_key']);

        $accessTokenLifetime = sprintf('PT%dS', $config['access_token_lifetime']);
        $this->enableGrantType(
            $container,
            $authorizationServer,
            'client_credentials',
            ClientCredentialsGrant::class,
            $accessTokenLifetime
        );

        $container->setParameter(self::CORS_PREFLIGHT_MAX_AGE_PARAM, $config['cors']['preflight_max_age']);
        $container->setParameter(self::CORS_ALLOW_ORIGINS_PARAM, $config['cors']['allow_origins']);
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $config
     */
    private function configureResourceServer(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(self::PUBLIC_KEY_SERVICE)
            ->setArgument(0, $config['public_key']);
    }

    /**
     * @param ContainerBuilder $container
     * @param Definition       $authorizationServer
     * @param string           $grantType
     * @param string           $grantTypeClass
     * @param string           $accessTokenLifetime
     */
    private function enableGrantType(
        ContainerBuilder $container,
        Definition $authorizationServer,
        string $grantType,
        string $grantTypeClass,
        string $accessTokenLifetime
    ): void {
        $authorizationServer->addMethodCall('enableGrantType', [
            new Definition($grantTypeClass),
            new Definition(\DateInterval::class, [$accessTokenLifetime])
        ]);

        $container->setParameter(
            self::SUPPORTED_GRANT_TYPES_PARAM,
            array_merge($container->getParameter(self::SUPPORTED_GRANT_TYPES_PARAM), [$grantType])
        );
    }

    /**
     * @param ExtendedContainerBuilder $container
     */
    private function configureSecurityFirewalls(ExtendedContainerBuilder $container): void
    {
        $oauthFirewalls = [];
        $configs = $container->getExtensionConfig($this->getAlias());
        foreach ($configs as $config) {
            if (!empty($config['resource_server']['oauth_firewalls'])
                && is_array($config['resource_server']['oauth_firewalls'])
            ) {
                $oauthFirewalls[] = $config['resource_server']['oauth_firewalls'];
            }
        }
        $oauthFirewalls = array_unique(array_merge(...$oauthFirewalls));

        $securityConfigs = $container->getExtensionConfig('security');
        foreach ($oauthFirewalls as $firewallName) {
            if (!empty($securityConfigs[0]['firewalls'][$firewallName])) {
                $securityConfigs[0]['firewalls'][$firewallName]['oauth2'] = true;
            }
        }
        $container->setExtensionConfig('security', $securityConfigs);
    }
}
