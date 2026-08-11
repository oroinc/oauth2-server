<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v7_1_0_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds the flag that enables Session Transfer for individual OAuth applications.
 */
final class EnableSessionTransferForOAuthApplications implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $clientTable = $schema->getTable('oro_oauth2_client');

        if (!$clientTable->hasColumn('session_transfer_allowed')) {
            $clientTable->addColumn('session_transfer_allowed', 'boolean', ['default' => '0']);
        }
    }
}
