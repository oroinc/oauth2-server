<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Data\ORM;

use Oro\Bundle\EmailBundle\Migrations\Data\ORM\AbstractEmailFixture;

/**
 * Loads email templates for OAuth 2.0 client entity.
 */
class LoadEmailTemplates extends AbstractEmailFixture
{
    /**
     * {@inheritDoc}
     */
    public function getEmailTemplatesList($dir): array
    {
        $templates = parent::getEmailTemplatesList($dir);
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            unset($templates['customerUser_created']);
        }

        return $templates;
    }

    /**
     * {@inheritDoc}
     */
    public function getEmailsDir(): string
    {
        return $this->container
            ->get('kernel')
            ->locateResource('@OroOAuth2ServerBundle/Migrations/Data/ORM/data/emails/client');
    }
}
