<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\CurrentClientProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

final class CurrentClientProviderTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private ManagerRegistry&MockObject $doctrine;

    private CurrentClientProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->provider = new CurrentClientProvider($this->tokenStorage, $this->doctrine);
    }

    public function testGetClientReturnsNullWithoutToken(): void
    {
        $this->doctrine
            ->expects(self::never())
            ->method('getManagerForClass');

        self::assertNull($this->provider->getClient());
    }

    public function testGetClientReturnsNullForTokenWithoutClientAttributes(): void
    {
        $this->tokenStorage->setToken(new OAuth2Token());

        $this->doctrine
            ->expects(self::never())
            ->method('getManagerForClass');

        self::assertNull($this->provider->getClient());
    }

    public function testGetClientReturnsClientOfClientAttribute(): void
    {
        $client = new Client();
        ReflectionUtil::setId($client, 30);
        $token = new OAuth2Token();
        $token->setAttribute(CurrentClientProvider::CLIENT_ATTRIBUTE, $client);
        $this->tokenStorage->setToken($token);

        $this->doctrine
            ->expects(self::never())
            ->method('getManagerForClass');

        self::assertSame($client, $this->provider->getClient());
    }

    public function testGetClientPrefersClientAttributeToClientIdAttribute(): void
    {
        $client = new Client();
        ReflectionUtil::setId($client, 30);
        $token = new OAuth2Token();
        $token->setAttribute(CurrentClientProvider::CLIENT_ATTRIBUTE, $client);
        $token->setAttribute(CurrentClientProvider::CLIENT_ID_ATTRIBUTE, 31);
        $this->tokenStorage->setToken($token);

        $this->doctrine
            ->expects(self::never())
            ->method('getManagerForClass');

        self::assertSame($client, $this->provider->getClient());
    }

    /**
     * @dataProvider getClientLoadsClientByClientIdAttributeProvider
     */
    public function testGetClientLoadsClientByClientIdAttribute(array $attributes): void
    {
        $client = new Client();
        ReflectionUtil::setId($client, 30);
        $token = new SessionTransferAuthenticationToken(new User(), 'main', new Organization());
        $token->setAttributes($attributes);
        $this->tokenStorage->setToken($token);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Client::class, 30)
            ->willReturn($client);
        $this->doctrine
            ->expects(self::once())
            ->method('getManagerForClass')
            ->with(Client::class)
            ->willReturn($entityManager);

        self::assertSame($client, $this->provider->getClient());
    }

    public static function getClientLoadsClientByClientIdAttributeProvider(): iterable
    {
        yield 'client id attribute only' => [[CurrentClientProvider::CLIENT_ID_ATTRIBUTE => 30]];
        yield 'empty client attribute' => [[
            CurrentClientProvider::CLIENT_ATTRIBUTE => null,
            CurrentClientProvider::CLIENT_ID_ATTRIBUTE => 30,
        ]];
    }

    public function testGetClientReturnsNullWhenClientOfClientIdAttributeDoesNotExist(): void
    {
        $token = new SessionTransferAuthenticationToken(new User(), 'main', new Organization());
        $token->setAttribute(CurrentClientProvider::CLIENT_ID_ATTRIBUTE, 30);
        $this->tokenStorage->setToken($token);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Client::class, 30)
            ->willReturn(null);
        $this->doctrine
            ->expects(self::once())
            ->method('getManagerForClass')
            ->with(Client::class)
            ->willReturn($entityManager);

        self::assertNull($this->provider->getClient());
    }

    /**
     * @dataProvider getClientReturnsNullForInvalidClientAttributesProvider
     */
    public function testGetClientReturnsNullForInvalidClientAttributes(array $attributes): void
    {
        $token = new SessionTransferAuthenticationToken(new User(), 'main', new Organization());
        $token->setAttributes($attributes);
        $this->tokenStorage->setToken($token);

        $this->doctrine
            ->expects(self::never())
            ->method('getManagerForClass');

        self::assertNull($this->provider->getClient());
    }

    public static function getClientReturnsNullForInvalidClientAttributesProvider(): iterable
    {
        yield 'client attribute is not a client' => [[CurrentClientProvider::CLIENT_ATTRIBUTE => 30]];
        yield 'client id attribute is a numeric string' => [[CurrentClientProvider::CLIENT_ID_ATTRIBUTE => '30']];
        yield 'client id attribute is null' => [[CurrentClientProvider::CLIENT_ID_ATTRIBUTE => null]];
        yield 'client id attribute is a client' => [[CurrentClientProvider::CLIENT_ID_ATTRIBUTE => new Client()]];
    }
}
