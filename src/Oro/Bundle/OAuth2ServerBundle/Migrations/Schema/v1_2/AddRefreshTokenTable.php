<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds "oro_oauth2_refresh_token" table
 */
class AddRefreshTokenTable implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->createRefreshTokenTable($schema);
        $this->addRefreshTokenForeignKeys($schema);
    }

    /**
     * Create oro_oauth2_refresh_token table
     */
    protected function createRefreshTokenTable(Schema $schema)
    {
        $table = $schema->createTable('oro_oauth2_refresh_token');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('identifier', 'string', ['length' => 80]);
        $table->addColumn('expires_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->addColumn('access_token_id', 'integer', ['notnull' => false]);
        $table->addColumn('revoked', 'boolean', ['default' => '0']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier'], 'oro_oauth2_refresh_token_uidx');
        $table->addIndex(['access_token_id'], 'IDX_5C4260E32CCB2688', []);
    }

    /**
     * Add oro_oauth2_refresh_token foreign keys.
     */
    protected function addRefreshTokenForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_oauth2_refresh_token');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_oauth2_access_token'),
            ['access_token_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }
}
