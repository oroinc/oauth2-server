<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Schema\v7_0_3_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EmailBundle\Migration\SetEmailImmutableQuery;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;

/**
 * Marks sensitive fields of the OAuth 2.0 Client entity as immutable in email templates.
 */
class MarkEmailFieldsAsImmutable implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $queries->addQuery(
            new SetEmailImmutableQuery(
                entityClass: Client::class,
                fieldNames: ['secret', 'salt']
            )
        );
    }
}
