<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadAuthorizationCodeGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AuthorizationCodeGrantServerTest extends OAuthServerTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadAuthorizationCodeGrantClient::class, LoadUser::class]);
    }

    private function getBearerAuthHeaderValue(): string
    {
        $responseData = $this->sendAccessTokenRequest();

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    private function sendAuthCodeRequest(string $clientId, string $redirectUri = null, bool $grant = true): Response
    {
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
                'oro_oauth2_server_authenticate',
                $requestParameters
            ),
            [
                'grantAccess' => $grant ? 'true' : 'false',
                '_csrf_token' => self::getContainer()
                    ->get('security.csrf.token_manager')
                    ->getToken('authorize_client')
                    ->getValue()
            ]
        );

        return $this->client->getResponse();
    }

    private function getAuthCode(string $clientId, string $redirectUri): string
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest($clientId, $redirectUri, true);

        $parameters = [];
        self::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
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
            $code = $this->getAuthCode(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, 'http://test.com');
        }

        return $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
            ],
            $expectedStatusCode
        );
    }

    public function testGetAuthCode()
    {
        $code = $this->getAuthCode(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, 'http://test.com');

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetAuthCodeWithoutRedirectURI()
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            null
        );

        $parameters = [];
        self::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        $code = $parameters['code'];
        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testTryToGetAuthCodeWithNotGrantedRequest()
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com',
            false
        );

        $parameters = [];
        self::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
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

    public function testTryToGetAuthCodeWithWithWrongRedirectUri()
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://wrong.com',
            true
        );

        self::assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
    }

    public function testTryToGetAuthCodeWithWithWrongClientId()
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            'wrong_client',
            'http://test.com',
            true
        );

        self::assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
    }

    public function testGetAuthToken()
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
        self::assertEquals(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);

        $client = $this->getReference(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testApiRequestWithCorrectAccessTokenShouldReturnRequestedData()
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();
        $expectedData = [
            'data' => [
                'type' => 'users',
                'id'   => '<toString(@user->id)>'
            ]
        ];

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);

        // test that the same access token can be used several times (until it will not be expired)
        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
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
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
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

    public function tryToOpenLoginPageWithoutAdditionalData()
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_login_form')
        );

        self::assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }
}
