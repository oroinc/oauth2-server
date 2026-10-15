<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v7_1_0_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds the flag that allows the logout for individual OAuth applications
 * and the list of URIs these applications are allowed to be redirected to after logout.
 */
final class AddLogoutToOAuthApplications implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $clientTable = $schema->getTable('oro_oauth2_client');

        if (!$clientTable->hasColumn('logout_allowed')) {
            $clientTable->addColumn('logout_allowed', 'boolean', ['default' => '0']);
        }
        if (!$clientTable->hasColumn('post_logout_redirect_uris')) {
            $clientTable->addColumn(
                'post_logout_redirect_uris',
                'simple_array',
                ['notnull' => false, 'comment' => '(DC2Type:simple_array)']
            );
        }
    }
}
