<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\EmailBundle\Event\EmailTemplateContextCollectEvent;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

/**
 * Sets a relevant website to an email template criteria context.
 */
class EmailTemplateContextCollectWebsiteAwareEventListener
{
    public function onContextCollect(EmailTemplateContextCollectEvent $event): void
    {
        $templateParams = $event->getTemplateParams();
        if (!isset($templateParams['entity']) ||
            !$templateParams['entity'] instanceof Client ||
            $event->getTemplateContextParameter('website') !== null) {
            return;
        }

        $website = null;
        if (method_exists($templateParams['entity'], 'getCustomerUser')) {
            $website = $templateParams['entity']->getCustomerUser()?->getWebsite();
        }

        if ($website !== null) {
            $event->setTemplateContextParameter('website', $website);
        }
    }
}
