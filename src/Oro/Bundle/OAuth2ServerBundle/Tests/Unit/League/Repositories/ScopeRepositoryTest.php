<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authentication\Token;

use Oro\Bundle\OAuth2ServerBundle\League\Entities\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entities\ScopeEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repositories\ScopeRepository;

class ScopeRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var ScopeRepository */
    private $repository;

    protected function setUp()
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
