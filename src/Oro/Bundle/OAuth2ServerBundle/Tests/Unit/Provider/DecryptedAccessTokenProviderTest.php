<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use Oro\Bundle\OAuth2ServerBundle\League\CryptKeyFile;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\Provider\DecryptedAccessTokenProvider;
use Oro\Component\Testing\TempDirExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DecryptedAccessTokenProviderTest extends TestCase
{
    use AccessTokenTrait;
    use TempDirExtension;

    private ClientEntity $client;
    private DecryptedAccessTokenProvider $provider;
    private string $accessToken;

    #[\Override]
    protected function setUp(): void
    {
        $tempDir = $this->getTempDir('someDir');
        $privateKeyPath = $tempDir . '/private_key.key';
        (new Process(['openssl', 'genrsa', '-out', $privateKeyPath, '2048']))->run();

        $publicKeyPath = $tempDir . '/public_key.key';
        (new Process(['openssl', 'rsa', '-in', $privateKeyPath, '-pubout', '-out', $publicKeyPath]))->run();

        $this->client = new ClientEntity();
        $this->client->setIdentifier('client_identifier');
        $this->setPrivateKey(new CryptKey($privateKeyPath));

        $this->cryptKeyFile = new CryptKeyFile($publicKeyPath);
        $this->provider = new DecryptedAccessTokenProvider($this->cryptKeyFile);

        $this->initJwtConfiguration();
        $this->accessToken = $this->convertToJWT()->toString();
    }

    public function testGetDecryptedBearerToken(): void
    {
        $decryptedToken = $this->provider->getDecryptedAccessToken($this->accessToken);
        self::assertEquals(
            $this->jwtConfiguration->parser()->parse($this->accessToken),
            $decryptedToken
        );

        $decryptedTokenData = $decryptedToken->claims()->all();
        self::assertEquals([$this->getClient()->getIdentifier()], $decryptedTokenData['aud']);
        self::assertEquals($this->getIdentifier(), $decryptedTokenData['jti']);
        self::assertEquals($this->getUserIdentifier(), $decryptedTokenData['sub']);
        self::assertEquals($this->getExpiryDateTime(), $decryptedTokenData['exp']);
        self::assertEquals($this->getScopes(), $decryptedTokenData['scopes']);
    }

    public function testGetDecryptedBearerTokenReturnsNullWhenFailedToDecrypt(): void
    {
        self::assertNull($this->provider->getDecryptedAccessToken('invalid_token'));
    }

    #[\Override]
    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    #[\Override]
    public function getExpiryDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today +1 day');
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return 'sample_user';
    }

    #[\Override]
    public function getScopes(): array
    {
        return ['sample_scope'];
    }

    #[\Override]
    public function getIdentifier(): string
    {
        return 'sample_identifier';
    }
}
