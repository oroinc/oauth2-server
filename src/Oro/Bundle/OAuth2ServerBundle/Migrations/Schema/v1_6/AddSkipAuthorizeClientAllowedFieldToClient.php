<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_6;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds skipAuthorizeClientAllowed field to Client entity
 */
class AddSkipAuthorizeClientAllowedFieldToClient implements Migration
{
    /**
     * {@inheritDoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_oauth2_client');

        if (!$table->hasColumn('skip_authorize_client_allowed')) {
            $table->addColumn('skip_authorize_client_allowed', 'boolean', ['default' => '0']);
        }
    }
}
