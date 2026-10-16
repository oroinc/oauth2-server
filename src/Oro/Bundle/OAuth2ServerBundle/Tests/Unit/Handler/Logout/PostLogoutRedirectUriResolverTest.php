<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Handler\Logout;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Handler\Logout\Exception\InvalidLogoutRequestException;
use Oro\Bundle\OAuth2ServerBundle\Handler\Logout\PostLogoutRedirectUriResolver;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PostLogoutRedirectUriResolverTest extends TestCase
{
    private const string CLIENT_ID = 'test_client';
    private const string REDIRECT_URI = 'https://rp.example/callback';
    private const string POST_LOGOUT_REDIRECT_URI = 'https://rp.example/logged-out';
    private const string ANOTHER_POST_LOGOUT_REDIRECT_URI = 'https://rp.example/bye';
    private const string LOOPBACK_POST_LOGOUT_REDIRECT_URI = 'http://127.0.0.1:8000/logged-out';

    private ClientRepositoryInterface&MockObject $clientRepository;
    private PostLogoutRedirectUriResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);

        $this->resolver = new PostLogoutRedirectUriResolver($this->clientRepository);
    }

    /**
     * @dataProvider resolveReturnsRegisteredPostLogoutRedirectUriProvider
     */
    public function testResolveReturnsRegisteredPostLogoutRedirectUri(
        bool $frontend,
        array $grants,
        array $postLogoutRedirectUris,
        string $postLogoutRedirectUri
    ): void {
        $client = new ClientEntity();
        $client->setIdentifier(self::CLIENT_ID);
        $client->setRedirectUri([self::REDIRECT_URI]);
        $client->setFrontend($frontend);
        $client->setLogoutAllowed(true);
        $client->setGrants($grants);
        $client->setPostLogoutRedirectUris($postLogoutRedirectUris);

        $this->clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with(self::CLIENT_ID)
            ->willReturn($client);

        $request = new Request([
            'client_id' => self::CLIENT_ID,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ]);

        self::assertSame($postLogoutRedirectUri, $this->resolver->resolve($request, $frontend));
    }

    public static function resolveReturnsRegisteredPostLogoutRedirectUriProvider(): iterable
    {
        yield 'storefront application on the storefront endpoint' => [
            'frontend' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'postLogoutRedirectUri' => self::POST_LOGOUT_REDIRECT_URI,
        ];
        yield 'back-office application on the back-office endpoint' => [
            'frontend' => false,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'postLogoutRedirectUri' => self::POST_LOGOUT_REDIRECT_URI,
        ];
        yield 'one of several registered post-logout redirect URIs' => [
            'frontend' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI, self::ANOTHER_POST_LOGOUT_REDIRECT_URI],
            'postLogoutRedirectUri' => self::ANOTHER_POST_LOGOUT_REDIRECT_URI,
        ];
        yield 'application with several grants including authorization_code' => [
            'frontend' => true,
            'grants' => [Client::CLIENT_CREDENTIALS, Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'postLogoutRedirectUri' => self::POST_LOGOUT_REDIRECT_URI,
        ];
        yield 'loopback post-logout redirect URI with another port (RFC 8252)' => [
            'frontend' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::LOOPBACK_POST_LOGOUT_REDIRECT_URI],
            'postLogoutRedirectUri' => 'http://127.0.0.1:9000/logged-out',
        ];
    }

    public function testResolveRejectsUnknownClient(): void
    {
        $this->clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with(self::CLIENT_ID)
            ->willReturn(null);

        $request = new Request([
            'client_id' => self::CLIENT_ID,
            'post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI,
        ]);

        $this->expectExceptionObject(InvalidLogoutRequestException::unknownClient());

        $this->resolver->resolve($request, true);
    }

    public function testResolveRejectsClientThatIsNotOroClientEntity(): void
    {
        $this->clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with(self::CLIENT_ID)
            ->willReturn($this->createMock(ClientEntityInterface::class));

        $request = new Request([
            'client_id' => self::CLIENT_ID,
            'post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI,
        ]);

        $this->expectExceptionObject(InvalidLogoutRequestException::unknownClient());

        $this->resolver->resolve($request, true);
    }

    /**
     * @dataProvider resolveRejectsRequestWithoutClientIdWithoutClientLookupProvider
     */
    public function testResolveRejectsRequestWithoutClientIdWithoutClientLookup(array $query): void
    {
        $this->clientRepository->expects(self::never())
            ->method('getClientEntity');

        $this->expectExceptionObject(InvalidLogoutRequestException::unknownClient());

        $this->resolver->resolve(new Request($query), true);
    }

    public static function resolveRejectsRequestWithoutClientIdWithoutClientLookupProvider(): iterable
    {
        yield 'empty client_id' => [
            'query' => ['client_id' => '', 'post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
        ];
        yield 'no client_id' => ['query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI]];
        yield 'no parameters' => ['query' => []];
    }

    /**
     * @dataProvider resolveRejectsInvalidRequestProvider
     */
    public function testResolveRejectsInvalidRequest(
        bool $clientFrontend,
        bool $logoutAllowed,
        array $grants,
        array $postLogoutRedirectUris,
        bool $frontend,
        array $query,
        InvalidLogoutRequestException $expectedException
    ): void {
        $client = new ClientEntity();
        $client->setIdentifier(self::CLIENT_ID);
        $client->setRedirectUri([self::REDIRECT_URI]);
        $client->setFrontend($clientFrontend);
        $client->setLogoutAllowed($logoutAllowed);
        $client->setGrants($grants);
        $client->setPostLogoutRedirectUris($postLogoutRedirectUris);

        $this->clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with(self::CLIENT_ID)
            ->willReturn($client);

        $this->expectExceptionObject($expectedException);

        $this->resolver->resolve(new Request(array_merge(['client_id' => self::CLIENT_ID], $query)), $frontend);
    }

    public static function resolveRejectsInvalidRequestProvider(): iterable
    {
        yield 'back-office application on the storefront endpoint' => [
            'clientFrontend' => false,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::clientTypeMismatch(),
        ];
        yield 'storefront application on the back-office endpoint' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => false,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::clientTypeMismatch(),
        ];
        yield 'application that does not allow logout' => [
            'clientFrontend' => true,
            'logoutAllowed' => false,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::logoutNotAllowed(),
        ];
        yield 'application that allows logout but has no authorization_code grant' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::CLIENT_CREDENTIALS],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::authorizationCodeGrantNotSupported(),
        ];
        yield 'application that allows logout but has no grants' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::authorizationCodeGrantNotSupported(),
        ];
        yield 'unregistered post-logout redirect URI' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::ANOTHER_POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'registered post-logout redirect URI with an extra query string' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI . '?next=https://evil.example/'],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'registered post-logout redirect URI with a trailing slash' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI . '/'],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'login redirect URI is not a post-logout redirect URI' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'redirect_uri instead of post_logout_redirect_uri' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'empty post-logout redirect URI' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => ''],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'no post-logout redirect URI' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [self::POST_LOGOUT_REDIRECT_URI],
            'frontend' => true,
            'query' => [],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
        yield 'application without registered post-logout redirect URIs' => [
            'clientFrontend' => true,
            'logoutAllowed' => true,
            'grants' => [Client::AUTHORIZATION_CODE],
            'postLogoutRedirectUris' => [],
            'frontend' => true,
            'query' => ['post_logout_redirect_uri' => self::POST_LOGOUT_REDIRECT_URI],
            'expectedException' => InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri(),
        ];
    }
}
