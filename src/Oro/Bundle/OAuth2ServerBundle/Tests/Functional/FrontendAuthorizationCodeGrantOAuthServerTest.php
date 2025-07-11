<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadAuthorizationCodeGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendAuthorizationCodeGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FrontendAuthorizationCodeGrantOAuthServerTest extends OAuthServerTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
        $this->initClient();
        $this->loadFixtures([
            LoadAuthorizationCodeGrantClient::class,
            LoadFrontendAuthorizationCodeGrantClient::class,
            LoadUser::class,
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    private function sendAuthCodeRequest(
        string $type,
        string $clientId,
        ?string $redirectUri = null,
        bool $grant = true
    ): Response {
        $requestParameters = [
            'response_type' => 'code',
            'client_id' => $clientId
        ];
        if (null !== $redirectUri) {
            $requestParameters['redirect_uri'] = $redirectUri;
        }

        $this->client->request(
            'POST',
            $this->getUrl(
                'frontend' === $type ? 'oro_oauth2_server_frontend_authenticate' : 'oro_oauth2_server_authenticate',
                $requestParameters
            ),
            [
                'grantAccess' => $grant ? 'true' : 'false',
                '_csrf_token' => $this->getCsrfToken('authorize_client')->getValue()
            ]
        );

        return $this->client->getResponse();
    }

    private function getAuthCode(string $type, string $clientId, string $redirectUri): string
    {
        if ('frontend' === $type) {
            $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        } else {
            $this->initClient([], self::generateBasicAuthHeader());
        }

        $response = $this->sendAuthCodeRequest($type, $clientId, $redirectUri);

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        return $parameters['code'];
    }

    private function sendAccessTokenRequest(
        ?string $code,
        $redirectUri,
        int $expectedStatusCode = Response::HTTP_OK
    ): array {
        if (null === $code) {
            $code = $this->getAuthCode(
                'frontend',
                LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'http://test.com'
            );
        }

        return $this->sendTokenRequest(
            [
                'grant_type' => 'authorization_code',
                'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => $redirectUri
            ],
            $expectedStatusCode
        );
    }

    public function testFrontendAuthenticateRequest(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_frontend_authenticate',
                [
                    'response_type' => 'code',
                    'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                    'redirect_uri' => 'http://test.com'
                ]
            )
        );
        $response = $this->client->getResponse();
        self::assertHtmlResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testGetFrontendAuthCode(): void
    {
        $code = $this->getAuthCode(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetFrontendAuthCodeWithoutRedirectUri(): void
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID
        );

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        $code = $parameters['code'];
        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testTryToGetFrontendAuthCodeWithNotGrantedRequest(): void
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com',
            false
        );

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        self::assertEquals('access_denied', $parameters['error']);
        self::assertEquals(
            'The resource owner or authorization server denied the request.',
            $parameters['error_description']
        );
        self::assertEquals('The user denied the request', $parameters['hint']);
        self::assertEquals('The resource owner or authorization server denied the request.', $parameters['message']);
    }

    public function testTryToGetFrontendAuthCodeWithWrongRedirectUri(): void
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://wrong.com'
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertResponseHeader($response, 'WWW-Authenticate', 'Basic realm="OAuth"');
    }

    public function testTryToGetFrontendAuthCodeWithWrongClientId(): void
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            'wrong_client',
            'http://test.com'
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertResponseHeader($response, 'WWW-Authenticate', 'Basic realm="OAuth"');
    }

    public function testTryToGetFrontendAuthCodeOnBackendClient(): void
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
    }

    public function testTryToGetBackendAuthCodeOnFrontendClient(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            'backend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
    }

    public function testGetFrontendAuthToken(): void
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);

        [$accessTokenPart1, $accessTokenPart2] = explode('.', $accessToken['access_token']);
        $accessTokenPart1 = self::jsonToArray(base64_decode($accessTokenPart1));
        $accessTokenPart2 = self::jsonToArray(base64_decode($accessTokenPart2));
        self::assertEquals('JWT', $accessTokenPart1['typ']);
        self::assertEquals('RS256', $accessTokenPart1['alg']);
        self::assertEquals(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, $accessTokenPart2['aud']);

        $client = $this->getReference(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testTryToMakeApiRequestWithCorrectFrontendAccessTokenOnBackendApi(): void
    {
        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
    }

    public function testTryToGetAuthTokenWithWrongAuthCode(): void
    {
        $accessToken = $this->sendAccessTokenRequest(
            'wrong_code',
            'http://test.com',
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals('Cannot decrypt the authorization code', $accessToken['hint']);
    }

    public function testTryToGetAuthTokenTwiceWithSameAuthCode(): void
    {
        $code = $this->getAuthCode(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        $accessToken = $this->sendAccessTokenRequest($code, 'http://test.com');
        $accessToken1 = $this->sendAccessTokenRequest($code, 'http://test.com', Response::HTTP_BAD_REQUEST);

        self::assertArrayHasKey('access_token', $accessToken);
        self::assertArrayNotHasKey('access_token', $accessToken1);
        self::assertEquals('Authorization code has been revoked', $accessToken1['hint']);
    }

    public function testTryToGetAuthTokenWithWrongRedirectUri(): void
    {
        $accessToken = $this->sendAccessTokenRequest(
            null,
            'http://wrong.com',
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals('invalid_client', $accessToken['error']);
    }
}
