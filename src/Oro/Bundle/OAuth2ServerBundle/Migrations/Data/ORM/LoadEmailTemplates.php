<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Data\ORM;

use Oro\Bundle\EmailBundle\Migrations\Data\ORM\AbstractHashEmailMigration;
use Oro\Bundle\MigrationBundle\Fixture\VersionedFixtureInterface;

/**
 * Loads email templates for OAuth 2.0 client entity.
 */
class LoadEmailTemplates extends AbstractHashEmailMigration implements VersionedFixtureInterface
{
    #[\Override]
    public function getVersion()
    {
        return '1.2';
    }

    #[\Override]
    protected function getEmailHashesToUpdate(): array
    {
        return [
            'user_oauth_application_created' => [
                '80f01a02d61b5669de9b16fdcd946629', // 1.2
            ],
            'customer_user_oauth_application_created' => [
                '0705ea843a35f8ab65afbfe7626f076a', // 1.0
                '3864db00688660fe85c46232bc78ac9f', // 1.1
                '3864db00688660fe85c46232bc78ac9f', // 1.2
            ],
        ];
    }

    #[\Override]
    public function getEmailTemplatesList($dir): array
    {
        $templates = parent::getEmailTemplatesList($dir);
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            unset($templates['customer_user_oauth_application_created']);
        }

        return $templates;
    }

    #[\Override]
    public function getEmailsDir(): string
    {
        return $this->container
            ->get('kernel')
            ->locateResource('@OroOAuth2ServerBundle/Migrations/Data/ORM/data/emails/client');
    }
}
