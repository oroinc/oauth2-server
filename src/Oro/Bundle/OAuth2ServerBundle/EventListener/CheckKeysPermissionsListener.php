<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\InstallerBundle\InstallerEvent;
use Oro\Bundle\OAuth2ServerBundle\Command\CheckKeysPermissionsCommand;

/**
 * Checks if oAuth 2.0 private key permissions are secure after install ORO application
 */
class CheckKeysPermissionsListener
{
    public function onFinishApplicationEvent(InstallerEvent $event): void
    {
        $event->getCommandExecutor()->runCommand(CheckKeysPermissionsCommand::getDefaultName(), [
            '--ignore-errors' => true,
        ]);
    }
}
