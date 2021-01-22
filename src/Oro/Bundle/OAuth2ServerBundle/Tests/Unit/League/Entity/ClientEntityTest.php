<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Entity;

use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;

class ClientEntityTest extends \PHPUnit\Framework\TestCase
{
    public function testName()
    {
        $name = 'test';

        $entity = new ClientEntity();
        self::assertNull($entity->getName());

        $entity->setName($name);
        self::assertSame($name, $entity->getName());
    }

    public function testRedirectUri()
    {
        $uris = ['test'];

        $entity = new ClientEntity();
        self::assertNull($entity->getRedirectUri());

        $entity->setRedirectUri($uris);
        self::assertSame($uris, $entity->getRedirectUri());

        $entity->setRedirectUri($uris[0]);
        self::assertSame($uris[0], $entity->getRedirectUri());
    }

    public function testFrontend()
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isFrontend());

        $entity->setFrontend(true);
        self::assertTrue($entity->isFrontend());

        $entity->setFrontend(false);
        self::assertFalse($entity->isFrontend());
    }

    public function testPlainTextPkceAllowed()
    {
        $entity = new ClientEntity();
        self::assertFalse($entity->isPlainTextPkceAllowed());

        $entity->setPlainTextPkceAllowed(true);
        self::assertTrue($entity->isPlainTextPkceAllowed());

        $entity->setPlainTextPkceAllowed(false);
        self::assertFalse($entity->isPlainTextPkceAllowed());
    }
}
