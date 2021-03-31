<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds confidential and plainTextPkceAllowed fields to client entity.
 */
class AddPkceRelatedFieldsToClient implements Migration
{
    /**
     * {@inheritDoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_oauth2_client');

        if (!$table->hasColumn('confidential')) {
            $table->addColumn('confidential', 'boolean', ['default' => '1']);
        }

        if (!$table->hasColumn('plain_text_pkce_allowed')) {
            $table->addColumn('plain_text_pkce_allowed', 'boolean', ['default' => '0']);
        }
    }
}
