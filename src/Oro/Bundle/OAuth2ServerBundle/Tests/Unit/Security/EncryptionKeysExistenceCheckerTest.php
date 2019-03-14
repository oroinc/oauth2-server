<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;

class EncryptionKeysExistenceCheckerTest extends \PHPUnit\Framework\TestCase
{
    public function testExistingPrivateKey()
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key')
        );
        self::assertTrue($checker->isPrivateKeyExist());
    }

    public function testNotExistingPrivateKey()
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key')
        );
        self::assertFalse($checker->isPrivateKeyExist());
    }

    public function testExistingPublicKey()
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key')
        );
        self::assertTrue($checker->isPublicKeyExist());
    }

    public function testNotExistingPublicKey()
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key')
        );
        self::assertFalse($checker->isPublicKeyExist());
    }
}
