<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;
use PHPUnit\Framework\TestCase;

class EncryptionKeysExistenceCheckerTest extends TestCase
{
    public function testExistingPrivateKey(): void
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key')
        );
        self::assertTrue($checker->isPrivateKeyExist());
    }

    public function testNotExistingPrivateKey(): void
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key')
        );
        self::assertFalse($checker->isPrivateKeyExist());
    }

    public function testExistingPublicKey(): void
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key')
        );
        self::assertTrue($checker->isPublicKeyExist());
    }

    public function testNotExistingPublicKey(): void
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key')
        );
        self::assertFalse($checker->isPublicKeyExist());
    }

    public function testIsPrivateKeyNoSecure(): void
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key')
        );
        self::assertFalse($checker->isPrivateKeySecure());
    }

    public function testIsPrivateSecureKeyNotExist(): void
    {
        $checker = new EncryptionKeysExistenceChecker(
            new CryptKeyFile(__DIR__ . '/../Fixtures/not_existing.key'),
            new CryptKeyFile(__DIR__ . '/../Fixtures/test.key')
        );
        self::assertNull($checker->isPrivateKeySecure());
    }
}
