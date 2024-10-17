<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Oro\Bundle\OAuth2ServerBundle\Provider\DecryptedTokenProvider;
use PHPUnit\Framework\TestCase;

final class DecryptedTokenProviderTest extends TestCase
{
    private DecryptedTokenProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new DecryptedTokenProvider();
        $this->provider->setEncryptionKey('ThisTokenIsNotSoSecretChangeIt');
    }

    public function testGetDecryptedTokenReturnsNullWhenFailedToDecrypt(): void
    {
        self::assertNull($this->provider->getDecryptedToken('invalid_token'));
    }

    public function testGetDecryptedTokenReturnsData(): void
    {
        $token = 'def50200ceeac5b2070614fa74f2c2333187fdd5daa90bb06b473d09c936b44fa17e9a2cccd23b9189beced1e74cdc'
            . 'bbb2f405f9da18128c8f297756e0e9a379430fde13bfead1397727873d46c581c3eb9bd47c793d689af635f8d45744109d'
            . 'a633cf4737c7eb5e91e522ba4f7def18cfdf9cc3d87f4fb31cd90fa1e55e040c1c54650d9ffc41ffaf7aac570f063bfa4c'
            . '23848c5e2a5f362e1abf3806687a162dee7ea4b7f63752aa4e71f3087cd7bb91cd9da032565de3d1c9aedf3e45d2480eab'
            . '74b0387a891646569fbffbbac8fa2024e10680d6d82e7c1915e84f1292c84aaf009ee94bdd11bcb4f243e2328992e4abc91'
            . 'cba7b76abbc1a59ba0bc67613015eed60dee635e84e8d27930b4ea640467b05ce856d7eb8013bb8a4624c2220075259603f'
            . '5a6c836008f2998a52157a70457f84985069235da9e2732e238dac48c39b0f299f38b356b6bbb1f3b3764cc2352877c403b'
            . 'fd00fab7ddf49f6d800775bd5cd4dfa831c22ecbf3600a6fa14ff215f7a5e4f2fcd86304f59998fc451825360ca06db';

        self::assertEquals(
            [
                'client_id' => 'Vr3hjx4a7I32TtoCbI_FGmGbUd5-fFDq',
                'refresh_token_id' => '760e4375cca2053a28ac63fc1cb760b00cdadff0fb91015f77'
                    . '7704f1cf0641a61d3781af57cd2062',
                'access_token_id' => '7029c8d4dc3bcea1fb9c261b336bb15e0a7eacdfe4ab304c1e3'
                    . 'a6979d52e043c34dc172f6f5e38ba',
                'scopes' => [],
                'user_id' => 'admin',
                'expire_time' => 1728082973,
            ],
            $this->provider->getDecryptedToken($token)
        );
    }
}
