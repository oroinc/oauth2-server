<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroOAuth2ServerBundleInstaller implements Installation
{
    /**
     * {@inheritDoc}
     */
    public function getMigrationVersion(): string
    {
        return 'v1_6';
    }

    /**
     * {@inheritDoc}
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        /** Tables generation **/
        $this->createClientTable($schema);
        $this->createAccessTokenTable($schema);
        $this->createRefreshTokenTable($schema);
        $this->createAuthCodeTable($schema);

        /** Foreign keys generation **/
        $this->addClientForeignKeys($schema);
        $this->addAccessTokenForeignKeys($schema);
        $this->addRefreshTokenForeignKeys($schema);
        $this->addAuthCodeForeignKeys($schema);
    }

    /**
     * Create oro_oauth2_client table
     */
    private function createClientTable(Schema $schema): void
    {
        $table = $schema->createTable('oro_oauth2_client');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('identifier', 'string', ['length' => 32]);
        $table->addColumn('secret', 'string', ['length' => 128, 'notnull' => false]);
        $table->addColumn('salt', 'string', ['length' => 50]);
        $table->addColumn('grants', 'simple_array', ['comment' => '(DC2Type:simple_array)']);
        $table->addColumn('scopes', 'simple_array', ['notnull' => false, 'comment' => '(DC2Type:simple_array)']);
        $table->addColumn('redirect_uris', 'simple_array', ['notnull' => false, 'comment' => '(DC2Type:simple_array)']);
        $table->addColumn('active', 'boolean', ['default' => '1']);
        $table->addColumn('frontend', 'boolean', ['default' => '0']);
        $table->addColumn('organization_id', 'integer', ['notnull' => false]);
        $table->addColumn('owner_entity_class', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('owner_entity_id', 'integer', ['notnull' => false]);
        $table->addColumn('last_used_at', 'datetime', ['comment' => '(DC2Type:datetime)', 'notnull' => false]);
        $table->addColumn('confidential', 'boolean', ['default' => '1']);
        $table->addColumn('plain_text_pkce_allowed', 'boolean', ['default' => '0']);
        $table->addColumn('skip_authorize_client_allowed', 'boolean', ['default' => '0']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier'], 'oro_oauth2_client_uidx');
        $table->addIndex(['organization_id'], 'IDX_2A8C454232C8A3DE');
        $table->addIndex(['owner_entity_class', 'owner_entity_id'], 'oro_oauth2_client_owner_idx');
    }

    /**
     * Create oro_oauth2_access_token table
     */
    private function createAccessTokenTable(Schema $schema): void
    {
        $table = $schema->createTable('oro_oauth2_access_token');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('identifier', 'string', ['length' => 80]);
        $table->addColumn('expires_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->addColumn('scopes', 'simple_array', ['notnull' => false, 'comment' => '(DC2Type:simple_array)']);
        $table->addColumn('client_id', 'integer', ['notnull' => false]);
        $table->addColumn('user_identifier', 'string', ['notnull' => false, 'length' => 128]);
        $table->addColumn('revoked', 'boolean', ['default' => '0']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier'], 'oro_oauth2_access_token_uidx');
        $table->addIndex(['client_id'], 'IDX_DF211BA819EB6921');
    }

    /**
     * Create oro_oauth2_refresh_token table
     */
    private function createRefreshTokenTable(Schema $schema): void
    {
        $table = $schema->createTable('oro_oauth2_refresh_token');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('identifier', 'string', ['length' => 80]);
        $table->addColumn('expires_at', 'datetime', ['comment' => '(DC2Type:datetime)']);
        $table->addColumn('access_token_id', 'integer', ['notnull' => false]);
        $table->addColumn('revoked', 'boolean', ['default' => '0']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['identifier'], 'oro_oauth2_refresh_token_uidx');
        $table->addIndex(['access_token_id'], 'IDX_5C4260E32CCB2688');
    }

    /**
     * Create oro_oauth2_auth_code table
     */
    private function createAuthCodeTable(Schema $schema): void
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
    }

    /**
     * Add oro_oauth2_client foreign keys.
     */
    private function addClientForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_oauth2_client');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
    }

    /**
     * Add oro_oauth2_access_token foreign keys.
     */
    private function addAccessTokenForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_oauth2_access_token');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_oauth2_client'),
            ['client_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_oauth2_refresh_token foreign keys.
     */
    private function addRefreshTokenForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_oauth2_refresh_token');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_oauth2_access_token'),
            ['access_token_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_oauth2_auth_code foreign keys.
     */
    private function addAuthCodeForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_oauth2_auth_code');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_oauth2_client'),
            ['client_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }
}
