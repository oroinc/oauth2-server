<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v1_7;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddApisField implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('oro_oauth2_client');
        if (!$table->hasColumn('all_apis')) {
            $table->addColumn('all_apis', 'boolean', ['default' => '1']);
        }
        if (!$table->hasColumn('apis')) {
            $table->addColumn('apis', 'simple_array', ['notnull' => false, 'comment' => '(DC2Type:simple_array)']);
        }
    }
}
