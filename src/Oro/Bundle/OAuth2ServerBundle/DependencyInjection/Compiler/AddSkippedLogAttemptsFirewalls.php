<?php

namespace Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds the OAuth authorization firewall as skipped to not log the login attempts.
 */
class AddSkippedLogAttemptsFirewalls implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('oro_user.security.skipped_log_attempts_firewalls_provider')
            ->addMethodCall('addSkippedFirewall', ['oauth2_authorization_authenticate'])
            ->addMethodCall('addSkippedFirewall', ['oauth2_frontend_authorization_authenticate']);
    }
}
