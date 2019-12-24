<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds lastUsedAt field to client entity.
 */
class AddLastUsedAtField implements Migration
{
    /**
     * {@inheritDoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_oauth2_client');
        $table->addColumn('last_used_at', 'datetime', ['comment' => '(DC2Type:datetime)', 'notnull' => false]);
    }
}
