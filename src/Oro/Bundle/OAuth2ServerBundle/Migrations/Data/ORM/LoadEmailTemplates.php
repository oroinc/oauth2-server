<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Data\ORM;

use Oro\Bundle\EmailBundle\Migrations\Data\ORM\AbstractEmailFixture;

/**
 * Loads email templates for OAuth 2.0 client entity.
 */
class LoadEmailTemplates extends AbstractEmailFixture
{
    /**
     * @param string $dir
     * @return array
     */
    public function getEmailTemplatesList($dir)
    {
        $templates = parent::getEmailTemplatesList($dir);
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            unset($templates['customerUser_created']);
        }

        return $templates;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailsDir()
    {
        return $this->container
            ->get('kernel')
            ->locateResource('@OroOAuth2ServerBundle/Migrations/Data/ORM/data/emails/client');
    }
}
