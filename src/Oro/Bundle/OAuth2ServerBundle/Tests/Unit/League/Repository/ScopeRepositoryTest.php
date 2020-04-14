<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ScopeRepository;

class ScopeRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var ScopeRepository */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = new ScopeRepository();
    }

    public function testGetScopeEntityByIdentifier()
    {
        $identifier = 'test_id';

        $expectedScopeEntity = new ScopeEntity();
        $expectedScopeEntity->setIdentifier($identifier);

        self::assertEquals($expectedScopeEntity, $this->repository->getScopeEntityByIdentifier($identifier));
    }

    public function testFinalizeScopes()
    {
        $scopes = [new ScopeEntity(), new ScopeEntity()];

        self::assertSame($scopes, $this->repository->finalizeScopes($scopes, 'test', new ClientEntity()));
    }
}
