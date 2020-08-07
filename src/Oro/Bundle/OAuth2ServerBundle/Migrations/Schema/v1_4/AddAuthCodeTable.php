<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_4;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds "oro_oauth2_auth_code" table.
 */
class AddAuthCodeTable implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->createTable('oro_oauth2_auth_code');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('identifier', 'string', ['length' => 80]);
        $table->addColumn('expires_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->addColumn('scopes', 'simple_array', ['notnull' => false, 'comment' => '(DC2Type:simple_array)']);
        $table->addColumn('client_id', 'integer', ['notnull' => false]);
        $table->addColumn('user_identifier', 'string', ['notnull' => false, 'length' => 128]);
        $table->addColumn('revoked', 'boolean', ['default' => '0']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier'], 'oro_oauth2_auth_code_uidx');
        $table->addIndex(['client_id'], 'IDX_3C8751F219EB6921');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_oauth2_client'),
            ['client_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }
}
