<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadAuthorizationCodeGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AuthorizationCodeGrantServerTest extends OAuthServerTestCase
{
    private const WWW_AUTHENTICATE_HEADER_VALUE = 'Basic realm="OAuth"';

    /**
     * PKCE code verifier that was generated by the rules from
     * @see https://datatracker.ietf.org/doc/html/rfc7636#section-4.1
     */
    private const CODE_VERIFIER = 'P5CelXPAESKVn2ArcXGjFcOmEWLoO0IjR3ubUXHb9EI';

    /**
     * S256 PKCE code challenge that was generated from the code challenge by the rules from
     * @see https://datatracker.ietf.org/doc/html/rfc7636#section-4.2
     */
    private const CODE_CHALLENGE = 'dkimRr35tjRdwIqRLCENZkVa5p-EoH3i9-VbnNXlnuY';

    #[\Override]
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

    private function sendAuthCodeRequest(
        string $clientId,
        string $redirectUri = null,
        bool $grant = true,
        array $additionalParameters = []
    ): Response {
        $requestParameters = [
            'response_type' => 'code',
            'client_id'     => $clientId,
        ];
        if (null !== $redirectUri) {
            $requestParameters['redirect_uri'] = $redirectUri;
        }
        if (count($additionalParameters)) {
            $requestParameters = array_merge($requestParameters, $additionalParameters);
        }

        $this->client->request(
            'POST',
            $this->getUrl(
                'oro_oauth2_server_authenticate',
                $requestParameters
            ),
            [
                'grantAccess' => $grant ? 'true' : 'false',
                '_csrf_token' => $this->getCsrfToken('authorize_client')->getValue(),
            ]
        );

        return $this->client->getResponse();
    }

    private function getAuthCode(string $clientId, string $redirectUri, array $additionalParameters = []): string
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest($clientId, $redirectUri, true, $additionalParameters);

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

    public function testTryToAuthorizeWithEmptyRequest(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $this->client->request(
            'POST',
            $this->getUrl(
                'oro_oauth2_server_authenticate',
                []
            ),
            [
                'true',
                '_csrf_token' => $this->getCsrfToken('authorize_client')->getValue(),
            ]
        );

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_BAD_REQUEST);
        self::assertEquals(
            [
                'error' => 'unsupported_grant_type',
                'error_description' => 'The authorization grant type is not supported by the authorization server.',
                'hint' => 'Check that all required parameters have been provided',
                'message' => 'The authorization grant type is not supported by the authorization server.'
            ],
            self::jsonToArray($response->getContent())
        );
    }

    public function testGetAuthCode(): void
    {
        $code = $this->getAuthCode(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, 'http://test.com');

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetAuthCodeForNonConfidentialPlainClient(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'plain',
            ]
        );

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetAuthCodeForNonConfidentialPlainClientWithNonPlainChallenge(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'S256',
            ]
        );

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetAuthCodeForNonConfidentialNonPlainClient(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::NON_PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'S256',
            ]
        );

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testTryToGetAuthCodeForNonConfidentialNonPlainClientWithPlainChallenge(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());

        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::NON_PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            true,
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'plain',
            ]
        );

        $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('invalid_request', $content['error']);
        self::assertEquals('Plain code challenge method is not allowed for this client', $content['hint']);
    }

    public function testGetAuthCodeWithoutRedirectURI(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
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

    public function testTryToGetAuthCodeWithNotGrantedRequest(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
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

    public function testTryToGetAuthCodeWithWithWrongRedirectUri(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://wrong.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            .'"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertResponseHeader($response, 'WWW-Authenticate', self::WWW_AUTHENTICATE_HEADER_VALUE);
    }

    public function testTryToGetAuthCodeWithWithWrongClientId(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            'wrong_client',
            'http://test.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            .'"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertResponseHeader($response, 'WWW-Authenticate', self::WWW_AUTHENTICATE_HEADER_VALUE);
    }

    public function testTryToGetAuthCodeForPublicClientWithoutCodeChallenge(): void
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            true
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_BAD_REQUEST);
        $content = \json_decode($response->getContent(), true);
        self::assertEquals('invalid_request', $content['error']);
        self::assertEquals('Code challenge must be provided for public clients', $content['hint']);
    }

    public function testGetAuthToken(): void
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

    public function testApiRequestWithCorrectAccessTokenShouldReturnRequestedData(): void
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();
        $expectedData = [
            'data' => [
                'type' => 'users',
                'id'   => '<toString(@user->id)>',
            ],
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

    public function testApiRequestWithCorrectAccessTokenThatGetWithPlainCodedChallenge(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'plain'
            ]
        );
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
                'code'          => $code,
                'redirect_uri'  => 'http://test.com',
                'code_verifier' => self::CODE_CHALLENGE
            ]
        );

        $authorizationHeader = sprintf('Bearer %s', $accessToken['access_token']);
        $this->assertResponseContains(
            ['data' => ['type' => 'users', 'id'   => '<toString(@user->id)>']],
            $this->get(
                ['entity' => 'users', 'id' => '<toString(@user->id)>'],
                [],
                ['HTTP_AUTHORIZATION' => $authorizationHeader]
            )
        );
    }

    public function testApiRequestWithCorrectAccessTokenThatGetWithCodedChallengeForConfidentialClient(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'S256'
            ]
        );
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'code'          => $code,
                'redirect_uri'  => 'http://test.com',
                'code_verifier' => self::CODE_VERIFIER,
                'client_secret' => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET
            ]
        );

        $authorizationHeader = sprintf('Bearer %s', $accessToken['access_token']);
        $this->assertResponseContains(
            ['data' => ['type' => 'users', 'id'   => '<toString(@user->id)>']],
            $this->get(
                ['entity' => 'users', 'id' => '<toString(@user->id)>'],
                [],
                ['HTTP_AUTHORIZATION' => $authorizationHeader]
            )
        );
    }

    public function testApiRequestWithCorrectAccessTokenThatGetWithS256CodedChallenge(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::NON_PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge'        => self::CODE_CHALLENGE,
                'code_challenge_method' => 'S256'
            ]
        );
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::NON_PLAIN_CLIENT_CLIENT_ID,
                'code'          => $code,
                'redirect_uri'  => 'http://test.com',
                'code_verifier' => self::CODE_VERIFIER
            ]
        );

        $authorizationHeader = sprintf('Bearer %s', $accessToken['access_token']);
        $this->assertResponseContains(
            ['data' => ['type' => 'users', 'id'   => '<toString(@user->id)>']],
            $this->get(
                ['entity' => 'users', 'id' => '<toString(@user->id)>'],
                [],
                ['HTTP_AUTHORIZATION' => $authorizationHeader]
            )
        );

        $newAccessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadAuthorizationCodeGrantClient::NON_PLAIN_CLIENT_CLIENT_ID,
                'refresh_token' => $accessToken['refresh_token'],
                'redirect_uri'  => 'http://test.com'
            ]
        );

        self::assertNotEquals($accessToken['access_token'], $newAccessToken['access_token']);
        self::assertNotEquals($accessToken['refresh_token'], $newAccessToken['refresh_token']);
    }

    public function testTryToGetAccessTokenForPublicClientWithoutCodeVerifier(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge' => self::CODE_CHALLENGE
            ]
        );
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
                'code'          => $code,
                'redirect_uri'  => 'http://test.com'
            ],
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals('invalid_request', $accessToken['error']);
        self::assertEquals('Check the `code_verifier` parameter', $accessToken['hint']);
    }

    public function testTryToGetAccessTokenForAnotherClient(): void
    {
        $code = $this->getAuthCode(
            LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID,
            'http://test.com',
            [
                'code_challenge' => self::CODE_CHALLENGE
            ]
        );
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::PLAIN_CLIENT_CLIENT_ID1,
                'code'          => $code,
                'redirect_uri'  => 'http://test.com',
                'code_verifier' => self::CODE_VERIFIER
            ],
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals('invalid_request', $accessToken['error']);
        self::assertEquals('Authorization code was not issued to this client', $accessToken['hint']);
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
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        $accessToken = $this->sendAccessTokenRequest($code, 'http://test.com');
        $accessToken1 = $this->sendAccessTokenRequest($code, 'http://test.com', Response::HTTP_BAD_REQUEST);

        self::assertArrayHasKey('access_token', $accessToken);
        self::assertArrayNotHasKey('access_token', $accessToken1);
        self::assertEquals('Authorization code has been revoked', $accessToken1['hint']);
    }

    public function testTryToGetAuthTokenWithWrongRedirectURI(): void
    {
        $accessToken = $this->sendAccessTokenRequest(
            null,
            'http://wrong.com',
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals('invalid_client', $accessToken['error']);
    }

    public function testTryToOpenLoginPageWithoutAdditionalData(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_login_form')
        );

        self::assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testTryToOpenLoginPageWithoutClientIdParameter(): void
    {
        $session = $this->createSession();
        $session->set('_security.oauth2_authorization_authenticate.target_path', 'http://test.com');
        $session->save();

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_login_form')
        );

        self::assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testTryToOpenLoginPageWithWrongClientIdParameter(): void
    {
        $session = $this->createSession();
        $session->set('_security.oauth2_authorization_authenticate.target_path', 'http://test.com?client_id=wrong');
        $session->save();

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_login_form')
        );

        self::assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testOpenLoginPage(): void
    {
        $session = $this->createSession();
        $session->set(
            '_security.oauth2_authorization_authenticate.target_path',
            'http://test.com?client_id=OxvBGZ4Z0gG6Maihm2amg80LcSpJez4'
        );
        $session->save();

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_login_form')
        );

        self::assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testTryToAuthorizeWithPotentialRedirect(): void
    {
        $apiUrl = self::getConfigManager('global')->get('oro_website.url');
        $path = $this->getUrl('oro_oauth2_server_authenticate', []);
        $url = "http://test.com" . $path;
        $potentialRedirectUrl = $apiUrl . $path;

        self::assertNotSame($url, $potentialRedirectUrl);

        $this->initClient([], self::generateBasicAuthHeader());
        $this->client->request(
            'POST',
            $url,
            [
                'true',
                '_csrf_token' => $this->getCsrfToken('authorize_client')->getValue(),
            ]
        );

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeNotEquals($response, Response::HTTP_FOUND);
        self::assertStringNotContainsString(
            "Redirecting to $potentialRedirectUrl",
            strip_tags($response->getContent())
        );
    }
}
