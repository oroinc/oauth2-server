<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ScopeEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ScopeRepository;
use PHPUnit\Framework\TestCase;

class ScopeRepositoryTest extends TestCase
{
    private ScopeRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ScopeRepository();
    }

    public function testGetScopeEntityByIdentifier(): void
    {
        $identifier = 'test_id';

        $expectedScopeEntity = new ScopeEntity();
        $expectedScopeEntity->setIdentifier($identifier);

        self::assertEquals($expectedScopeEntity, $this->repository->getScopeEntityByIdentifier($identifier));
    }

    public function testFinalizeScopes(): void
    {
        $scopes = [new ScopeEntity(), new ScopeEntity()];

        self::assertSame($scopes, $this->repository->finalizeScopes($scopes, 'test', new ClientEntity()));
    }
}
