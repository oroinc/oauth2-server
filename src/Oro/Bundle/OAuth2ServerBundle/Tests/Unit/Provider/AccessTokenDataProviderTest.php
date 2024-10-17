<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken as AccessTokenEntity;
use Oro\Bundle\OAuth2ServerBundle\Provider\AccessTokenDataProvider;
use Oro\Bundle\OAuth2ServerBundle\Provider\DecryptedAccessTokenProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AccessTokenDataProviderTest extends TestCase
{
    private DecryptedAccessTokenProvider|MockObject $decryptedAccessTokenProvider;

    private ObjectRepository|MockObject $repository;

    private AccessTokenDataProvider $accessTokenDataProvider;

    protected function setUp(): void
    {
        $this->decryptedAccessTokenProvider = $this->createMock(DecryptedAccessTokenProvider::class);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $this->repository = $this->createMock(ObjectRepository::class);

        $doctrine
            ->method('getRepository')
            ->with(AccessTokenEntity::class)
            ->willReturn($this->repository);

        $this->accessTokenDataProvider = new AccessTokenDataProvider(
            $this->decryptedAccessTokenProvider,
            $doctrine
        );
    }

    public function testGetAccessTokenDataReturnsData(): void
    {
        $accessToken = 'test_token';
        $claims = [
            'jti' => 'token_id',
            'aud' => ['client_id'],
            'sub' => 'user_id',
            'scopes' => ['all'],
            'exp' => new DateTimeImmutable(),
            'iat' => new DateTimeImmutable(),
            'nbf' => new DateTimeImmutable(),
        ];

        $decryptedToken = $this->createMock(UnencryptedToken::class);
        $dataSet = new DataSet($claims, '');
        $decryptedToken
            ->expects(self::once())
            ->method('claims')
            ->willReturn($dataSet);

        $this->decryptedAccessTokenProvider
            ->expects(self::once())
            ->method('getDecryptedAccessToken')
            ->with($accessToken)
            ->willReturn($decryptedToken);

        $result = $this->accessTokenDataProvider->getAccessTokenData($accessToken);

        self::assertEquals($claims, $result);
    }

    public function testGetAccessTokenDataReturnsNullOnFailure(): void
    {
        $accessToken = 'test_token';

        $this->decryptedAccessTokenProvider
            ->expects(self::once())
            ->method('getDecryptedAccessToken')
            ->with($accessToken)
            ->willReturn(null);

        $this->repository
            ->expects(self::never())
            ->method('findOneBy');

        $result = $this->accessTokenDataProvider->getAccessTokenData($accessToken);

        self::assertNull($result);
    }

    public function testGetAccessTokenEntityReturnsEntity(): void
    {
        $accessToken = 'test_token';
        $claims = ['jti' => 'token_id'];
        $accessTokenEntity = $this->createMock(AccessTokenEntity::class);

        $decryptedToken = $this->createMock(UnencryptedToken::class);
        $dataSet = new DataSet($claims, '');
        $decryptedToken
            ->expects(self::once())
            ->method('claims')
            ->willReturn($dataSet);

        $this->decryptedAccessTokenProvider
            ->expects(self::once())
            ->method('getDecryptedAccessToken')
            ->willReturn($decryptedToken);

        $this->repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $claims['jti']])
            ->willReturn($accessTokenEntity);

        $result = $this->accessTokenDataProvider->getAccessTokenEntity($accessToken);

        self::assertSame($accessTokenEntity, $result);
    }

    public function testGetAccessTokenEntityReturnsNullWhenNoJti(): void
    {
        $accessToken = 'test_token';
        $claims = ['aud' => ['client_id']];

        $decryptedToken = $this->createMock(UnencryptedToken::class);
        $dataSet = new DataSet($claims, '');
        $decryptedToken
            ->expects(self::once())
            ->method('claims')
            ->willReturn($dataSet);

        $this->decryptedAccessTokenProvider
            ->expects(self::once())
            ->method('getDecryptedAccessToken')
            ->willReturn($decryptedToken);

        $this->repository
            ->expects(self::never())
            ->method('findOneBy');

        $result = $this->accessTokenDataProvider->getAccessTokenEntity($accessToken);

        self::assertNull($result);
    }

    public function testGetAccessTokenEntityWithValidTokenButNoEntityFound(): void
    {
        $accessToken = 'test_token';
        $claims = ['jti' => 'token_id'];

        $decryptedToken = $this->createMock(UnencryptedToken::class);
        $dataSet = new DataSet($claims, '');
        $decryptedToken
            ->expects(self::once())
            ->method('claims')
            ->willReturn($dataSet);

        $this->decryptedAccessTokenProvider
            ->expects(self::once())
            ->method('getDecryptedAccessToken')
            ->willReturn($decryptedToken);

        $this->repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => $claims['jti']])
            ->willReturn(null);

        $result = $this->accessTokenDataProvider->getAccessTokenEntity($accessToken);

        self::assertNull($result);
    }
}
