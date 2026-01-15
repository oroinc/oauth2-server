<?php

namespace Oro\Bundle\OAuth2ServerBundle\Datagrid\EventListener;

use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;

/**
 * Hide password grant type applications if the login form feature is disabled.
 */
class PasswordApplicationsGridListener
{
    public function __construct(
        private FeatureChecker $featureChecker
    ) {
    }

    public function onBuildBefore(BuildBefore $event): void
    {
        // do not disable password grant for frontend applications
        if ($event->getDatagrid()->getParameters()->get('frontend', false)) {
            return;
        }

        if ($this->featureChecker->isFeatureEnabled('user_login_password')) {
            return;
        }

        $config = $event->getConfig();
        $query = $config->getOrmQuery();
        $query->addAndWhere('client.grants != \'password\'');
    }
}
