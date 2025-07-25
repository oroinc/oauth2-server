<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\InstallerBundle\InstallerEvent;

/**
 * Checks if OAuth 2.0 private key permissions are secure after install ORO application
 */
class CheckKeysPermissionsListener
{
    public function onFinishApplicationEvent(InstallerEvent $event): void
    {
        $event->getCommandExecutor()->runCommand('oro:cron:oauth-server:check-keys-permissions', [
            '--ignore-errors' => true,
        ]);
    }
}
