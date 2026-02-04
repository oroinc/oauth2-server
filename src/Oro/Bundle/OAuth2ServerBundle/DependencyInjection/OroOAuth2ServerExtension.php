<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Oro\Bundle\OAuth2ServerBundle\Controller\MetadataController;
use Oro\Bundle\OAuth2ServerBundle\Controller\ProtectedResourceController;
use Oro\Bundle\OAuth2ServerBundle\Controller\WellKnownMetadataController;
use Oro\Bundle\OAuth2ServerBundle\League\Grant\AuthCodeGrant;
use Oro\Bundle\OAuth2ServerBundle\League\Grant\PasswordGrant;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendAuthCodeRepository;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendRefreshTokenRepository;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\FrontendUserRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Component\DependencyInjection\ExtendedContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class OroOAuth2ServerExtension extends Extension implements PrependExtensionInterface
{
    public const AUTH_SERVER_REFRESH_TOKEN_LIFETIME = 'oro_oauth2_server.authorization_server.refresh_token_lifetime';
    public const AUTH_SERVER_ACCESS_TOKEN_LIFETIME = 'oro_oauth2_server.authorization_server.access_token_lifetime';

    private const SUPPORTED_GRANT_TYPES_PARAM = 'oro_oauth2_server.supported_grant_types';
    private const SUPPORTED_CLIENT_OWNERS_PARAM = 'oro_oauth2_server.supported_client_owners';
    private const CORS_PREFLIGHT_MAX_AGE_PARAM = 'oro_oauth2_server.cors.preflight_max_age';
    private const CORS_ALLOW_ORIGINS_PARAM = 'oro_oauth2_server.cors.allow_origins';
    private const CORS_ALLOW_HEADERS_PARAM = 'oro_oauth2_server.cors.allow_headers';

    private const PRIVATE_KEY_SERVICE = 'oro_oauth2_server.league.private_key';
    private const PUBLIC_KEY_SERVICE = 'oro_oauth2_server.league.public_key';

    private const AUTHORIZATION_SERVER_SERVICE = 'oro_oauth2_server.league.authorization_server';
    private const USER_REPOSITORY_SERVICE = 'oro_oauth2_server.league.repository.user_repository';
    private const REFRESH_TOKEN_REPOSITORY_SERVICE = 'oro_oauth2_server.league.repository.refresh_token_repository';
    private const AUTH_CODE_REPOSITORY_SERVICE = 'oro_oauth2_server.league.repository.auth_code_repository';
    private const FRONTEND_USER_LOADER_SERVICE = 'oro_customer.security.user_loader';
    private const CUSTOMER_VISITOR_MANAGER_SERVICE = 'oro_customer.customer_visitor_manager';
    private const CUSTOMER_LOGIN_SOURCES = 'oro_customer_user.login_sources';
    private const FEATURE_CHECK_SERVICE = 'oro_featuretoggle.checker.feature_checker';

    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('services_api.yml');
        $loader->load('controllers.yml');
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $loader->load('services_frontend.yml');
        } else {
            $loader->load('services_no_frontend.yml');
        }

        if ('test' === $container->getParameter('kernel.environment')) {
            $keysFileLocator = new FileLocator(__DIR__ . '/../Tests/Functional/Environment/keys');
            $config['authorization_server']['private_key'] = $keysFileLocator->locate('private.key');
            $config['resource_server']['public_key'] = $keysFileLocator->locate('public.key');
        }

        $this->configureSupportedClientOwners($container);
        $this->configureUserRepository($container);
        $this->configureAuthorizationServer($container, $config['authorization_server']);
        $this->configureResourceServer($container, $config['resource_server']);
        $this->configureProtectedResources($container, $config['protected_resources']);
        $this->configureWellKnownMetadataController($container);
        $this->configureCustomerUserLoginAttempts($container);
        $this->configureAuthCodeGrantSuccessHandler($container, $config['authorization_server']);
        $this->configureAuthCodeLogAttemptHelper($container, $config['authorization_server']);
        $this->configureDecryptedTokenProvider($container, $config['authorization_server']);

        $container->setParameter(
            self::AUTH_SERVER_REFRESH_TOKEN_LIFETIME,
            $config['authorization_server']['refresh_token_lifetime']
        );
        $container->setParameter(
            self::AUTH_SERVER_ACCESS_TOKEN_LIFETIME,
            $config['authorization_server']['access_token_lifetime']
        );
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        if ($container instanceof ExtendedContainerBuilder) {
            $this->configureSecurityFirewalls($container);
            $this->reconfigureLoginFirewalls($container);
        }

        $this->configureOrganizationProBundle($container);

        if ('test' === $container->getParameter('kernel.environment')) {
            $fileLocator = new FileLocator(__DIR__ . '/../Tests/Functional/Environment');
            $configData = Yaml::parse(file_get_contents($fileLocator->locate('app.yml')));
            foreach ($configData as $name => $config) {
                $container->prependExtensionConfig($name, $config);
            }
            $this->configureTestProtectedResources($container);
        }
    }

    #[\Override]
    public function getAlias(): string
    {
        return Configuration::ROOT_NODE;
    }

    private function configureSupportedClientOwners(ContainerBuilder $container): void
    {
        // add CustomerUser here to avoid creation of a bridge for the customer-portal package
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $container->setParameter(
                self::SUPPORTED_CLIENT_OWNERS_PARAM,
                array_merge(
                    $container->getParameter(self::SUPPORTED_CLIENT_OWNERS_PARAM),
                    ['customerUser' => 'Oro\Bundle\CustomerBundle\Entity\CustomerUser']
                )
            );
        }
    }

    private function configureUserRepository(ContainerBuilder $container): void
    {
        // replace user and refresh token repositories with the repositories that can handle customer users here
        // to avoid creation of a bridge for the customer-portal package
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $container->getDefinition(self::USER_REPOSITORY_SERVICE)
                ->setClass(FrontendUserRepository::class)
                ->addArgument(new Reference(self::FRONTEND_USER_LOADER_SERVICE))
                ->addArgument(new Reference(self::CUSTOMER_VISITOR_MANAGER_SERVICE));
            $container->getDefinition(self::REFRESH_TOKEN_REPOSITORY_SERVICE)
                ->setClass(FrontendRefreshTokenRepository::class)
                ->addArgument(new Reference(self::FRONTEND_USER_LOADER_SERVICE));
            $container->getDefinition(self::AUTH_CODE_REPOSITORY_SERVICE)
                ->setClass(FrontendAuthCodeRepository::class)
                ->addArgument(new Reference(self::FRONTEND_USER_LOADER_SERVICE));
        }
    }

    private function configureAuthorizationServer(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(self::PRIVATE_KEY_SERVICE)
            ->setArgument('$keyPath', $config['private_key']);

        $privateKey = new Definition(
            CryptKey::class,
            [new Expression(\sprintf('service("%s").getKeyPath()', self::PRIVATE_KEY_SERVICE)), null, false]
        );
        $authorizationServer = $container->getDefinition(self::AUTHORIZATION_SERVER_SERVICE)
            ->setArgument('$privateKey', $privateKey)
            ->setArgument('$encryptionKey', $config['encryption_key']);

        $accessTokenLifetime = $this->getTokenLifetime($config['access_token_lifetime']);
        $refreshTokenEnabled = $config['enable_refresh_token'];
        $refreshTokenLifetime = $refreshTokenEnabled
            ? $this->getTokenLifetime($config['refresh_token_lifetime'])
            : null;
        $authCodeEnabled = $config['enable_auth_code'];
        $authCodeLifetime = $refreshTokenEnabled
            ? $this->getTokenLifetime($config['auth_code_lifetime'])
            : null;

        $this->enableGrantType(
            $container,
            $authorizationServer,
            'client_credentials',
            $this->getClientCredentialsGrant(),
            $accessTokenLifetime
        );

        $this->enableGrantType(
            $container,
            $authorizationServer,
            'password',
            $this->getPasswordGrant(),
            $accessTokenLifetime,
            $refreshTokenLifetime
        );

        if ($refreshTokenEnabled) {
            $this->enableGrantType(
                $container,
                $authorizationServer,
                'refresh_token',
                $this->getRefreshTokenGrant(),
                $accessTokenLifetime,
                $refreshTokenLifetime,
                false
            );
        }

        if ($authCodeEnabled) {
            $this->enableGrantType(
                $container,
                $authorizationServer,
                'authorization_code',
                $this->getAuthCodeGrant($authCodeLifetime),
                $accessTokenLifetime,
                $refreshTokenLifetime
            );
        }

        $container->setParameter(self::CORS_PREFLIGHT_MAX_AGE_PARAM, $config['cors']['preflight_max_age']);
        $container->setParameter(self::CORS_ALLOW_ORIGINS_PARAM, $config['cors']['allow_origins']);
        $container->setParameter(self::CORS_ALLOW_HEADERS_PARAM, $config['cors']['allow_headers']);
    }

    private function configureResourceServer(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(self::PUBLIC_KEY_SERVICE)
            ->setArgument('$keyPath', $config['public_key']);
    }

    private function configureProtectedResources(ContainerBuilder $container, array $protectedResources): void
    {
        $container->getDefinition('oro_oauth2_server.protected_resource_provider')
            ->setArgument('$resources', $protectedResources);

        $authorizationServerRoutes = [];
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $authorizationServerRoutes['%web_backend_prefix%/'] = 'oro_oauth2_server_metadata';
            $authorizationServerRoutes[''] = 'oro_oauth2_server_frontend_metadata';
        } else {
            $authorizationServerRoutes[''] = 'oro_oauth2_server_metadata';
        }
        $container->getDefinition(ProtectedResourceController::class)
            ->setArgument('$authorizationServerRoutes', $authorizationServerRoutes);
    }

    private function configureWellKnownMetadataController(ContainerBuilder $container): void
    {
        $controllers = [];
        $controllerRefs = [];
        $metadataPrefix = '/oauth2-server';
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $controllers['%web_backend_prefix%' . $metadataPrefix] = 'backend';
            $controllerRefs['backend'] = new Reference(MetadataController::class);
            $controllers[$metadataPrefix] = 'frontend';
            $controllerRefs['frontend'] = new Reference('oro_oauth2_server.frontend_metadata_controller');
        } else {
            $controllers[$metadataPrefix] = 'default';
            $controllerRefs['default'] = new Reference(MetadataController::class);
        }
        $container->getDefinition(WellKnownMetadataController::class)
            ->setArgument('$metadataControllers', $controllers)
            ->setArgument('$container', ServiceLocatorTagPass::register($container, $controllerRefs));
    }

    private function getTokenLifetime(int $lifetimeInSeconds): string
    {
        return \sprintf('PT%dS', $lifetimeInSeconds);
    }

    private function enableGrantType(
        ContainerBuilder $container,
        Definition $authorizationServer,
        string $grantTypeName,
        Definition $grantType,
        string $accessTokenLifetime,
        ?string $refreshTokenLifetime = null,
        bool $addToVisibleList = true
    ): void {
        if ($refreshTokenLifetime) {
            $grantType->addMethodCall('setRefreshTokenTTL', [
                new Definition(\DateInterval::class, [$refreshTokenLifetime])
            ]);
        }

        $authorizationServer->addMethodCall('enableGrantType', [
            $grantType,
            new Definition(\DateInterval::class, [$accessTokenLifetime])
        ]);

        if ($addToVisibleList) {
            $container->setParameter(
                self::SUPPORTED_GRANT_TYPES_PARAM,
                array_merge($container->getParameter(self::SUPPORTED_GRANT_TYPES_PARAM), [$grantTypeName])
            );
        }
    }

    private function getClientCredentialsGrant(): Definition
    {
        return new Definition(ClientCredentialsGrant::class);
    }

    private function getPasswordGrant(): Definition
    {
        return new Definition(PasswordGrant::class, [
            new Reference(self::USER_REPOSITORY_SERVICE),
            new Reference(self::REFRESH_TOKEN_REPOSITORY_SERVICE),
            new Reference(self::FEATURE_CHECK_SERVICE)
        ]);
    }

    private function getRefreshTokenGrant(): Definition
    {
        return new Definition(RefreshTokenGrant::class, [
            new Reference(self::REFRESH_TOKEN_REPOSITORY_SERVICE)
        ]);
    }

    private function getAuthCodeGrant(string $authCodeLifetime): Definition
    {
        return new Definition(AuthCodeGrant::class, [
            new Reference(self::AUTH_CODE_REPOSITORY_SERVICE),
            new Reference(self::REFRESH_TOKEN_REPOSITORY_SERVICE),
            new Definition(\DateInterval::class, [$authCodeLifetime])
        ]);
    }

    private function configureSecurityFirewalls(ExtendedContainerBuilder $container): void
    {
        $oauthFirewalls = [];
        $configs = $container->getExtensionConfig($this->getAlias());
        foreach ($configs as $config) {
            if (
                !empty($config['resource_server']['oauth_firewalls'])
                && \is_array($config['resource_server']['oauth_firewalls'])
            ) {
                $oauthFirewalls[] = $config['resource_server']['oauth_firewalls'];
            }
        }
        $oauthFirewalls = array_unique(array_merge(...$oauthFirewalls));

        $securityConfigs = $container->getExtensionConfig('security');
        foreach ($oauthFirewalls as $firewallName) {
            if (!empty($securityConfigs[0]['firewalls'][$firewallName])) {
                $config = [];
                if (!empty($securityConfigs[0]['firewalls'][$firewallName]['anonymous_customer_user'])) {
                    $config ['anonymous_customer_user'] = true;
                }

                $securityConfigs[0]['firewalls'][$firewallName]['oauth2'] = $config;
            }
        }
        $container->setExtensionConfig('security', $securityConfigs);
    }

    private function reconfigureLoginFirewalls(ExtendedContainerBuilder $container): void
    {
        $securityConfigs = $container->getExtensionConfig('security');
        $oroSecurityConfigs = $container->getExtensionConfig('oro_security');
        $firewalls = $securityConfigs[0]['firewalls'];

        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $firewalls = $this->addOAuthFirewalls(
                $firewalls,
                Yaml::parseFile(__DIR__ . '/../Resources/config/oro/frontend_firewalls.yml')
            );

            // add access_control configs for frontend
            $accessControlConfig = Yaml::parseFile(__DIR__ . '/../Resources/config/oro/frontend_access_control.yml');
            foreach ($accessControlConfig as &$accessControl) {
                if (!isset($accessControl['options']['frontend']) || false === $accessControl['options']['frontend']) {
                    $accessControl['path'] = \sprintf(
                        '^%s%s',
                        '%web_backend_prefix%',
                        ltrim($accessControl['path'], '^')
                    );
                }
                if (isset($accessControl['options'])) {
                    unset($accessControl['options']);
                }
            }
            unset($accessControl);
            $oroSecurityConfigs[0]['access_control'] = array_merge(
                $accessControlConfig,
                $oroSecurityConfigs[0]['access_control']
            );
            $securityConfigs[0]['access_control'] = array_merge(
                $accessControlConfig,
                $securityConfigs[0]['access_control']
            );
        } else {
            $firewalls = $this->addOAuthFirewalls(
                $firewalls,
                Yaml::parseFile(__DIR__ . '/../Resources/config/oro/no_frontend_firewalls.yml')
            );
        }

        $securityConfigs[0]['firewalls'] = $firewalls;
        $container->setExtensionConfig('oro_security', $oroSecurityConfigs);
        $container->setExtensionConfig('security', $securityConfigs);
    }

    private function addOAuthFirewalls(array $firewalls, array $oauthFirewalls): array
    {
        $resultFirewalls = [];
        foreach ($firewalls as $firewallName => $firewall) {
            $resultFirewalls[$firewallName] = $firewall;
            if ('oauth2_authorization_server' === $firewallName) {
                foreach ($oauthFirewalls as $oauthFirewallName => $oauthFirewall) {
                    $resultFirewalls[$oauthFirewallName] = $oauthFirewall;
                }
            }
        }

        return $resultFirewalls;
    }

    private function configureOrganizationProBundle(ContainerBuilder $container): void
    {
        if (!class_exists('\Oro\Bundle\OrganizationProBundle\OroOrganizationProBundle')) {
            return;
        }

        $container->prependExtensionConfig(
            'oro_organization_pro',
            ['ignore_preferred_organization_tokens' => [OAuth2Token::class]]
        );
    }

    private function configureCustomerUserLoginAttempts(ContainerBuilder $container): void
    {
        // add the OAuth login source here to avoid creation of a bridge for the customer-portal package
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $container->setParameter(
                self::CUSTOMER_LOGIN_SOURCES,
                array_merge(
                    $container->getParameter(self::CUSTOMER_LOGIN_SOURCES),
                    ['OAuth' => ['label' => 'oro.oauth2server.login_source.oauth', 'code' => 20]]
                )
            );
        }
    }

    private function configureAuthCodeGrantSuccessHandler(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition('oro_oauth2_server.handler.get_access_token.auth_code_grant_success_handler')
            ->addMethodCall('setEncryptionKey', [$config['encryption_key']]);
    }

    private function configureAuthCodeLogAttemptHelper(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition('oro_oauth2_server.auth_code_log_attempt.helper')
            ->addMethodCall('setEncryptionKey', [$config['encryption_key']]);
    }

    private function configureDecryptedTokenProvider(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition('oro_oauth2_server.provider.decrypted_token')
            ->addMethodCall('setEncryptionKey', [$config['encryption_key']]);
    }

    private function configureTestProtectedResources(ContainerBuilder $container): void
    {
        $testResourcePath = '/user/create';
        $testResourceWithRouteParametersPath = '/user/update/1';
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $testResourcePath = '%web_backend_prefix%' . $testResourcePath;
            $testResourceWithRouteParametersPath = '%web_backend_prefix%' . $testResourceWithRouteParametersPath;
        }
        $protectedResources = [
            $testResourcePath => [
                'name' => 'Test Resource',
                'route' => 'oro_user_create',
                'supported_scopes' => ['scope:test'],
                'options' => ['x-test-option' => 'test resource']
            ],
            $testResourceWithRouteParametersPath => [
                'name' => 'Test Resource With Route Parameters',
                'route' => 'oro_user_update',
                'route_params' => ['id' => 1],
                'supported_scopes' => ['scope:test'],
                'options' => ['x-test-option' => 'test resource with route_params']
            ]
        ];
        if (class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $protectedResources['/customer/user/create'] = [
                'name' => 'Test Frontend Resource',
                'route' => 'oro_customer_frontend_customer_user_create',
                'supported_scopes' => ['scope:test'],
                'options' => ['x-test-option' => 'test frontend resource']
            ];
        }
        $container->prependExtensionConfig($this->getAlias(), ['protected_resources' => $protectedResources]);
    }
}
