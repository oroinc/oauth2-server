<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v7_1_0_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Creates the table that stores short-lived Session Transfer Tokens.
 */
final class AddSessionTransferTokenTable implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        if ($schema->hasTable('oro_oauth2_session_transfer_token')) {
            return;
        }
        $table = $schema->createTable('oro_oauth2_session_transfer_token');

        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('identifier', 'string', ['length' => 64]);
        $table->addColumn('client_id', 'integer');
        $table->addColumn('source_access_token_identifier', 'string', ['length' => 80]);
        $table->addColumn('user_identifier', 'string', ['length' => 128]);
        $table->addColumn('organization_id', 'integer');
        $table->addColumn('route', 'string', ['length' => 255]);
        $table->addColumn('route_parameters', 'json', ['comment' => '(DC2Type:json)']);
        $table->addColumn('context_data', 'json', ['comment' => '(DC2Type:json)']);
        $table->addColumn('created_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->addColumn('expires_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->addColumn(
            'consumed_at',
            'datetime',
            ['notnull' => false, 'comment' => '(DC2Type:datetime)']
        );
        $table->addColumn('revoked', 'boolean', ['default' => '0']);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier'], 'oro_oauth2_stt_uidx');
        $table->addIndex(['client_id'], 'oro_oauth2_stt_client_idx');
        $table->addIndex(['organization_id'], 'oro_oauth2_stt_org_idx');
        $table->addIndex(['expires_at'], 'oro_oauth2_stt_exp_idx');
        $table->addIndex(['consumed_at'], 'oro_oauth2_stt_consumed_idx');
        $table->addIndex(['source_access_token_identifier'], 'oro_oauth2_stt_source_idx');

        $table->addForeignKeyConstraint(
            $schema->getTable('oro_oauth2_client'),
            ['client_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }
}
