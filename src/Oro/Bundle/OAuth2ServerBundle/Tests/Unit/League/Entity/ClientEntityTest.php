<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Entity;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use PHPUnit\Framework\TestCase;

class ClientEntityTest extends TestCase
{
    public function testIdentifier(): void
    {
        $identifier = 'test';

        $entity = new ClientEntity();
        self::assertNull($entity->getIdentifier());

        $entity->setIdentifier($identifier);
        self::assertSame($identifier, $entity->getIdentifier());
    }

    public function testName(): void
    {
        $name = 'test';

        $entity = new ClientEntity();
        self::assertNull($entity->getName());

        $entity->setName($name);
        self::assertSame($name, $entity->getName());
    }

    public function testRedirectUri(): void
    {
        $uris = ['test'];

        $entity = new ClientEntity();
        self::assertNull($entity->getRedirectUri());

        $entity->setRedirectUri($uris);
        self::assertSame($uris, $entity->getRedirectUri());

        $entity->setRedirectUri($uris[0]);
        self::assertSame($uris[0], $entity->getRedirectUri());
    }

    public function testConfidential(): void
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isConfidential());

        $entity->setConfidential(true);
        self::assertTrue($entity->isConfidential());

        $entity->setConfidential(false);
        self::assertFalse($entity->isConfidential());
    }

    public function testFrontend(): void
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isFrontend());

        $entity->setFrontend(true);
        self::assertTrue($entity->isFrontend());

        $entity->setFrontend(false);
        self::assertFalse($entity->isFrontend());
    }

    public function testPlainTextPkceAllowed(): void
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isPlainTextPkceAllowed());

        $entity->setPlainTextPkceAllowed(true);
        self::assertTrue($entity->isPlainTextPkceAllowed());

        $entity->setPlainTextPkceAllowed(false);
        self::assertFalse($entity->isPlainTextPkceAllowed());
    }

    public function testSkipAuthorizeClientAllowed(): void
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isSkipAuthorizeClientAllowed());

        $entity->setSkipAuthorizeClientAllowed(true);
        self::assertTrue($entity->isSkipAuthorizeClientAllowed());

        $entity->setSkipAuthorizeClientAllowed(false);
        self::assertFalse($entity->isSkipAuthorizeClientAllowed());
    }

    public function testLogoutAllowed(): void
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isLogoutAllowed());

        $entity->setLogoutAllowed(true);
        self::assertTrue($entity->isLogoutAllowed());

        $entity->setLogoutAllowed(false);
        self::assertFalse($entity->isLogoutAllowed());
    }

    public function testPostLogoutRedirectUris(): void
    {
        $uris = ['https://example.com/'];

        $entity = new ClientEntity();
        self::assertSame([], $entity->getPostLogoutRedirectUris());

        $entity->setPostLogoutRedirectUris($uris);
        self::assertSame($uris, $entity->getPostLogoutRedirectUris());
    }

    public function testGrants(): void
    {
        $grants = ['authorization_code', 'client_credentials'];

        $entity = new ClientEntity();
        self::assertSame([], $entity->getGrants());

        $entity->setGrants($grants);
        self::assertSame($grants, $entity->getGrants());
    }

    public function testApis(): void
    {
        $apis = ['test'];

        $entity = new ClientEntity();
        self::assertSame([], $entity->getApis());

        $entity->setApis($apis);
        self::assertSame($apis, $entity->getApis());
    }
}
