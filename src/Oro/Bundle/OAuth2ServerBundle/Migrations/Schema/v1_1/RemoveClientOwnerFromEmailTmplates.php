<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\OroOptions;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Removes "ownerEntityClass" and "ownerEntityId" fields of Oro\Bundle\OAuth2ServerBundle\Entity\Client entity
 * from the list of variables for email templates.
 */
class RemoveClientOwnerFromEmailTmplates implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_oauth2_client');
        $this->removeFieldFromEmailTemplateVariables($table, 'owner_entity_class', 'ownerEntityClass');
        $this->removeFieldFromEmailTemplateVariables($table, 'owner_entity_id', 'ownerEntityId');
    }

    private function removeFieldFromEmailTemplateVariables(Table $table, string $columnName, string $fieldName): void
    {
        $table->getColumn($columnName)->setOptions([
            OroOptions::KEY => [
                ExtendOptionsManager::FIELD_NAME_OPTION => $fieldName,
                'email'                                 => [
                    'available_in_template' => false
                ]
            ]
        ]);
    }
}
