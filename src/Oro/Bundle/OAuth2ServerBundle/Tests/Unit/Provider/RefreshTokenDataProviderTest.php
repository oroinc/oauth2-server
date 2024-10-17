<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken as RefreshTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\Provider\DecryptedTokenProvider;
use Oro\Bundle\OAuth2ServerBundle\Provider\RefreshTokenDataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RefreshTokenDataProviderTest extends TestCase
{
    private DecryptedTokenProvider|MockObject $decryptedTokenProvider;

    private ManagerRegistry|MockObject $doctrine;

    private ObjectRepository|MockObject $repository;

    private RefreshTokenDataProvider $provider;

    protected function setUp(): void
    {
        $this->decryptedTokenProvider = $this->createMock(DecryptedTokenProvider::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->repository = $this->createMock(ObjectRepository::class);
        $this->doctrine
            ->method('getRepository')
            ->with(RefreshTokenEntity::class)
            ->willReturn($this->repository);

        $this->provider = new RefreshTokenDataProvider($this->decryptedTokenProvider, $this->doctrine);
    }

    public function testGetRefreshTokenDataWithValidToken(): void
    {
        $refreshToken = 'valid-refresh-token';
        $decryptedToken = [
            'refresh_token_id' => 'refresh-token-id',
            'client_id' => 'client-id',
            'expire_time' => 1234567890,
            'user_id' => 'user-id',
        ];

        $this->decryptedTokenProvider
            ->expects(self::once())
            ->method('getDecryptedToken')
            ->with($refreshToken)
            ->willReturn($decryptedToken);

        $result = $this->provider->getRefreshTokenData($refreshToken);

        self::assertSame($decryptedToken, $result);
    }

    public function testGetRefreshTokenDataWithInvalidToken(): void
    {
        $refreshToken = 'invalid-refresh-token';

        $this->decryptedTokenProvider
            ->expects(self::once())
            ->method('getDecryptedToken')
            ->with($refreshToken)
            ->willReturn(null);

        $result = $this->provider->getRefreshTokenData($refreshToken);

        self::assertNull($result);
    }

    public function testGetRefreshTokenEntityWithValidToken(): void
    {
        $refreshToken = 'valid-refresh-token';
        $decryptedToken = [
            'refresh_token_id' => 'refresh-token-id',
        ];
        $refreshTokenEntity = new RefreshTokenEntity(
            $decryptedToken['refresh_token_id'],
            new \DateTime(),
            $this->createMock(AccessToken::class)
        );

        $this->decryptedTokenProvider
            ->expects(self::once())
            ->method('getDecryptedToken')
            ->with($refreshToken)
            ->willReturn($decryptedToken);

        $this->repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $decryptedToken['refresh_token_id']])
            ->willReturn($refreshTokenEntity);

        $result = $this->provider->getRefreshTokenEntity($refreshToken);

        self::assertSame($refreshTokenEntity, $result);
    }

    public function testGetRefreshTokenEntityWithInvalidToken(): void
    {
        $refreshToken = 'invalid-refresh-token';

        $this->decryptedTokenProvider
            ->expects(self::once())
            ->method('getDecryptedToken')
            ->with($refreshToken)
            ->willReturn([]);

        $this->repository
            ->expects(self::never())
            ->method('findOneBy');

        $result = $this->provider->getRefreshTokenEntity($refreshToken);

        self::assertNull($result);
    }

    public function testGetRefreshTokenEntityWithValidTokenButNoEntityFound(): void
    {
        $refreshToken = 'valid-refresh-token';
        $decryptedToken = [
            'refresh_token_id' => 'refresh-token-id',
        ];

        $this->decryptedTokenProvider
            ->expects(self::once())
            ->method('getDecryptedToken')
            ->with($refreshToken)
            ->willReturn($decryptedToken);

        $this->repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $decryptedToken['refresh_token_id']])
            ->willReturn(null);

        $result = $this->provider->getRefreshTokenEntity($refreshToken);

        self::assertNull($result);
    }
}
