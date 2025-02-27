<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ClientCredentialsOAuthServerTest extends OAuthServerTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class]);
    }

    private function sendAccessTokenRequest(int $expectedStatusCode = Response::HTTP_OK, array $server = []): Response
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
            ],
            $server
        );

        self::assertResponseStatusCodeEquals($response, $expectedStatusCode);
        if (Response::HTTP_OK === $expectedStatusCode) {
            self::assertResponseContentTypeEquals($response, 'application/json; charset=UTF-8');
        } elseif ($expectedStatusCode >= Response::HTTP_BAD_REQUEST) {
            self::assertResponseContentTypeEquals($response, 'application/json');
        }

        return $response;
    }

    private function getBearerAuthHeaderValue(): string
    {
        $response = $this->sendAccessTokenRequest();
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseData = self::jsonToArray($response->getContent());

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    public function testGetAuthToken()
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        $response = $this->sendAccessTokenRequest();
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $accessToken = self::jsonToArray($response->getContent());

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        [$accessTokenFirstPart, $accessTokenSecondPart] = explode('.', $accessToken['access_token']);
        $accessTokenFirstPart = self::jsonToArray(base64_decode($accessTokenFirstPart));
        $accessTokenSecondPart = self::jsonToArray(base64_decode($accessTokenSecondPart));
        self::assertEquals('JWT', $accessTokenFirstPart['typ']);
        self::assertEquals('RS256', $accessTokenFirstPart['alg']);
        self::assertEquals(LoadClientCredentialsClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testGetAuthTokenWhenCredentialsProvidedViaBasicAuthorization()
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        $method = 'POST';
        $uri = $this->getUrl('oro_oauth2_server_auth_token');
        $server = [
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_ACCEPT'        => 'application/json,text/plain,*/*',
            'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode(
                LoadClientCredentialsClient::OAUTH_CLIENT_ID
                . ':'
                . LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
            )
        ];
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $server,
            'grant_type=client_credentials'
        );
        $this->assertSessionNotStarted($method, $uri, $server);
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
        self::assertResponseContentTypeEquals($response, 'application/json; charset=UTF-8');
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $accessToken = self::jsonToArray($response->getContent());

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        [$accessTokenFirstPart, $accessTokenSecondPart] = explode('.', $accessToken['access_token']);
        $accessTokenFirstPart = self::jsonToArray(base64_decode($accessTokenFirstPart));
        $accessTokenSecondPart = self::jsonToArray(base64_decode($accessTokenSecondPart));
        self::assertEquals('JWT', $accessTokenFirstPart['typ']);
        self::assertEquals('RS256', $accessTokenFirstPart['alg']);
        self::assertEquals(LoadClientCredentialsClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testGetAuthTokenForDeactivatedClient()
    {
        /** @var Client $client */
        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $response = $this->sendAccessTokenRequest(Response::HTTP_UNAUTHORIZED);
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseContent = self::jsonToArray($response->getContent());

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'message'           => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertNull($client->getLastUsedAt());
    }

    public function testGetAuthTokenWhenCredentialsAreNotProvided()
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            ['grant_type' => 'client_credentials']
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_BAD_REQUEST);
        self::assertResponseContentTypeEquals($response, 'application/json');
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseContent = self::jsonToArray($response->getContent());

        self::assertEquals(
            [
                'error'             => 'invalid_request',
                'message'           => 'The request is missing a required parameter,'
                    . ' includes an invalid parameter value, includes a parameter more than once,'
                    . ' or is otherwise malformed.',
                'error_description' => 'The request is missing a required parameter,'
                    . ' includes an invalid parameter value, includes a parameter more than once,'
                    . ' or is otherwise malformed.',
                'hint'              => 'Check the `client_id` parameter'
            ],
            $responseContent
        );
    }

    public function testGetAuthTokenWhenProvidedCredentialsAreEmpty()
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            ['grant_type' => 'client_credentials', 'client_id' => '', 'client_secret' => '']
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertResponseContentTypeEquals($response, 'application/json');
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseContent = self::jsonToArray($response->getContent());

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'message'           => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );
    }

    public function testGetAuthTokenForCorsRequest()
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $origin = 'https://oauth.test.com';

        $response = $this->sendAccessTokenRequest(Response::HTTP_OK, ['HTTP_Origin' => $origin]);
        self::assertEquals($origin, $response->headers->get('Access-Control-Allow-Origin'));

        $accessToken = self::jsonToArray($response->getContent());

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        [$accessTokenFirstPart, $accessTokenSecondPart] = explode('.', $accessToken['access_token']);
        $accessTokenFirstPart = self::jsonToArray(base64_decode($accessTokenFirstPart));
        $accessTokenSecondPart = self::jsonToArray(base64_decode($accessTokenSecondPart));
        self::assertEquals('JWT', $accessTokenFirstPart['typ']);
        self::assertEquals('RS256', $accessTokenFirstPart['alg']);
        self::assertEquals(LoadClientCredentialsClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testGetAuthTokenForDeactivatedClientForCorsRequest()
    {
        /** @var Client $client */
        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $origin = 'https://oauth.test.com';
        $response = $this->sendAccessTokenRequest(Response::HTTP_UNAUTHORIZED, ['HTTP_Origin' => $origin]);
        self::assertEquals($origin, $response->headers->get('Access-Control-Allow-Origin'));

        $responseContent = self::jsonToArray($response->getContent());

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'message'           => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertNull($client->getLastUsedAt());
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

    public function testApiRequestWithWrongAccessTokenShouldReturnUnauthorizedStatusCode()
    {
        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer WRONG_KEY'],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
    }

    public function testApiRequestWithCorrectAccessTokenButForDeactivatedClientShouldReturnUnauthorizedStatusCode()
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();

        /** @var Client $client */
        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
    }

    public function testOptionsRequest()
    {
        $response = $this->sendRequest(
            'OPTIONS',
            $this->getUrl('oro_oauth2_server_auth_token_options')
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
        self::assertAllowResponseHeader($response, 'OPTIONS, POST');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Origin');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Methods');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Headers');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Expose-Headers');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Max-Age');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Credentials');
    }

    public function methodsProvider(): array
    {
        return [
            ['ANOTHER'],
            ['OPTIONS'],
            ['GET'],
            ['POST'],
            ['PATCH'],
            ['DELETE']
        ];
    }

    /**
     * @dataProvider methodsProvider
     */
    public function testOptionsPreflightRequest(string $requestMethod)
    {
        $response = $this->sendRequest(
            'OPTIONS',
            $this->getUrl('oro_oauth2_server_auth_token_options'),
            [],
            [
                'HTTP_Origin'                        => 'https://oauth.test.com',
                'HTTP_Access-Control-Request-Method' => $requestMethod
            ]
        );

        self::assertResponseHeader($response, 'Access-Control-Allow-Origin', 'https://oauth.test.com');
        self::assertResponseHeader($response, 'Access-Control-Allow-Methods', 'OPTIONS, POST');
        self::assertResponseHeader($response, 'Access-Control-Allow-Headers', 'Authorization,Content-Type,X-Foo');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Expose-Headers');
        self::assertResponseHeader($response, 'Access-Control-Max-Age', 600);
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Credentials');
        self::assertResponseHeader($response, 'Cache-Control', 'max-age=600, public');
        self::assertResponseHeader($response, 'Vary', 'Origin');
        self::assertResponseHeaderNotExists($response, 'Allow');
    }

    public function testOptionsAsCorsButNotPreflightRequest()
    {
        $response = $this->sendRequest(
            'OPTIONS',
            $this->getUrl('oro_oauth2_server_auth_token_options'),
            [],
            [
                'HTTP_Origin' => 'https://oauth.test.com'
            ]
        );

        self::assertResponseHeader($response, 'Access-Control-Allow-Origin', 'https://oauth.test.com');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Methods');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Headers');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Expose-Headers');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Max-Age');
        self::assertResponseHeaderNotExists($response, 'Access-Control-Allow-Credentials');
        self::assertResponseHeader($response, 'Allow', 'OPTIONS, POST');
    }
}
