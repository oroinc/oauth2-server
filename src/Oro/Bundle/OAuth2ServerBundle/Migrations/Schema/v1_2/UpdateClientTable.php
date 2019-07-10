<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Sets nullable for "secret", "owner_entity_class", "owner_entity_id" columns and adds "active" column.
 */
class UpdateClientTable implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_oauth2_client');

        $table->getColumn('secret')->setNotnull(false);
        $table->getColumn('owner_entity_class')->setNotnull(false);
        $table->getColumn('owner_entity_id')->setNotnull(false);

        $table->addColumn('frontend', 'boolean', ['default' => '0']);
    }
}
