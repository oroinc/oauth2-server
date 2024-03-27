<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Data\ORM;

use Oro\Bundle\EmailBundle\Migrations\Data\ORM\AbstractHashEmailMigration;
use Oro\Bundle\MigrationBundle\Fixture\VersionedFixtureInterface;

/**
 * Loads email templates for OAuth 2.0 client entity.
 */
class LoadEmailTemplates extends AbstractHashEmailMigration implements VersionedFixtureInterface
{
    public function getVersion()
    {
        return '1.1';
    }

    protected function getEmailHashesToUpdate(): array
    {
        return [
            'user_oauth_application_created' => [],
            'customer_user_oauth_application_created' => [
                '0705ea843a35f8ab65afbfe7626f076a', // 1.0
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getEmailTemplatesList($dir): array
    {
        $templates = parent::getEmailTemplatesList($dir);
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            unset($templates['customer_user_oauth_application_created']);
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
