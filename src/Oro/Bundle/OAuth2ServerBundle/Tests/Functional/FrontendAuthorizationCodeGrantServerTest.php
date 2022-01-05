<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadAuthorizationCodeGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendAuthorizationCodeGrantClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FrontendAuthorizationCodeGrantServerTest extends OAuthServerTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }
        $this->initClient();
        $this->loadFixtures([
            LoadAuthorizationCodeGrantClient::class,
            LoadFrontendAuthorizationCodeGrantClient::class,
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    private function getBearerAuthHeaderValue(): string
    {
        $responseData = $this->sendAccessTokenRequest();

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    private function sendAuthCodeRequest(
        string $type,
        string $clientId,
        string $redirectUri = null,
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

        $response = $this->sendAuthCodeRequest($type, $clientId, $redirectUri, true);

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        return $parameters['code'];
    }

    private function sendAccessTokenRequest(
        string $code = null,
        $redirectUri = 'http://test.com',
        int $expectedStatusCode = Response::HTTP_OK
    ): array {
        if ($code === null) {
            $code = $this->getAuthCode(
                'frontend',
                LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'http://test.com'
            );
        }

        return $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
            ],
            $expectedStatusCode
        );
    }

    public function testGetFrontendAuthCode()
    {
        $code = $this->getAuthCode(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetFrontendAuthCodeWithoutRedirectURI()
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            null
        );

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        $code = $parameters['code'];
        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testTryToGetFrontendAuthCodeWithNotGrantedRequest()
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

    public function testTryToGetFrontendAuthCodeWithWithWrongRedirectUri()
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://wrong.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertResponseHeader($response, 'WWW-Authenticate', 'Basic realm="OAuth"');
    }

    public function testTryToGetFrontendAuthCodeWithWithWrongClientId()
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            'wrong_client',
            'http://test.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertResponseHeader($response, 'WWW-Authenticate', 'Basic realm="OAuth"');
    }

    public function testTryToGetFrontendAuthCodeOnBackendClient()
    {
        $this->initClient([], self::generateBasicAuthHeader('grzegorz.brzeczyszczykiewicz@example.com', 'test'));
        $response = $this->sendAuthCodeRequest(
            'frontend',
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
    }

    public function testTryToGetBackendAuthCodeOnFrontendClient()
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            'backend',
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
    }

    public function testGetFrontendAuthToken()
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $accessToken = $this->sendAccessTokenRequest();

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);

        [$accessTokenFirstPart, $accessTokenSecondPart] = explode('.', $accessToken['access_token']);
        $accessTokenFirstPart = self::jsonToArray(base64_decode($accessTokenFirstPart));
        $accessTokenSecondPart = self::jsonToArray(base64_decode($accessTokenSecondPart));
        self::assertEquals('JWT', $accessTokenFirstPart['typ']);
        self::assertEquals('RS256', $accessTokenFirstPart['alg']);
        self::assertEquals(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);

        $client = $this->getReference(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testTryToMakeApiRequestWithCorrectFrontendAccessTokenOnBackendApi()
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
        self::assertResponseHeader(
            $response,
            'WWW-Authenticate',
            'WSSE realm="Secured API", profile="UsernameToken"'
        );
    }

    public function testTryToGetAuthTokenWithWrongAuthCode()
    {
        $accessToken = $this->sendAccessTokenRequest(
            'wrong_code',
            'http://test.com',
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals('Cannot decrypt the authorization code', $accessToken['hint']);
    }

    public function testTryToGetAuthTokenTwiceWithSameAuthCode()
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

    public function testTryToGetAuthTokenWithWrongRedirectURI()
    {
        $accessToken = $this->sendAccessTokenRequest(
            null,
            'http://wrong.com',
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals('invalid_client', $accessToken['error']);
    }
}
