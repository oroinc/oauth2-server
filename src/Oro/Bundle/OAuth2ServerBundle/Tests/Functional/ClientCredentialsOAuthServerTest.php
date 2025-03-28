<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
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
        $this->loadFixtures([LoadClientCredentialsClient::class, LoadUser::class]);
    }

    private function sendAccessTokenRequest(int $expectedStatusCode = Response::HTTP_OK, array $server = []): Response
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            [
                'grant_type' => 'client_credentials',
                'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
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

    public function testGetAuthToken(): void
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

    public function testGetAuthTokenWhenCredentialsProvidedViaBasicAuthorization(): void
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        $method = 'POST';
        $uri = $this->getUrl('oro_oauth2_server_auth_token');
        $server = [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_ACCEPT' => 'application/json,text/plain,*/*',
            'HTTP_AUTHORIZATION' => 'Basic '
                . base64_encode(
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

    public function testGetAuthTokenForDeactivatedClient(): void
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
                'error' => 'invalid_client',
                'message' => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertNull($client->getLastUsedAt());
    }

    public function testGetAuthTokenWhenCredentialsAreNotProvided(): void
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
                'error' => 'invalid_request',
                'message' => 'The request is missing a required parameter,'
                    . ' includes an invalid parameter value, includes a parameter more than once,'
                    . ' or is otherwise malformed.',
                'error_description' => 'The request is missing a required parameter,'
                    . ' includes an invalid parameter value, includes a parameter more than once,'
                    . ' or is otherwise malformed.',
                'hint' => 'Check the `client_id` parameter'
            ],
            $responseContent
        );
    }

    public function testGetAuthTokenWhenProvidedCredentialsAreEmpty(): void
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
                'error' => 'invalid_client',
                'message' => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );
    }

    public function testGetAuthTokenForCorsRequest(): void
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

    public function testGetAuthTokenForDeactivatedClientForCorsRequest(): void
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
                'error' => 'invalid_client',
                'message' => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );

        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        self::assertNull($client->getLastUsedAt());
    }

    public function testApiRequestWithCorrectAccessTokenShouldReturnRequestedData(): void
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();
        $expectedData = [
            'data' => [
                'type' => 'users',
                'id' => '<toString(@user->id)>'
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

    public function testTryToSendApiRequestWithAccessTokenInURI(): void
    {
        $response = $this->sendAccessTokenRequest();
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseData = self::jsonToArray($response->getContent());

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>', 'access_token' => $responseData['access_token']],
            [],
            [],
            false
        );

        $this->assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testApiRequestWithCorrectAccessTokenInBody(): void
    {
        $response = $this->sendAccessTokenRequest();
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        $keyData = self::jsonToArray($response->getContent());

        $response = $this->patch(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [
                'access_token' => $keyData['access_token'],
                'data' => [
                    'type' => 'users',
                    'id' => '<toString(@user->id)>',
                    'attributes' => [
                        'firstName' => 'first_request'
                    ]
                ]
            ]
        );
        $this->assertResponseContains(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '<toString(@user->id)>',
                    'attributes' => [
                        'firstName' => 'first_request'
                    ]
                ]
            ],
            $response
        );
        self::assertArrayNotHasKey('access_token', self::jsonToArray($response->getContent()));

        // test that the same access token can be used several times (until it will not be expired)
        $response = $this->patch(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [
                'access_token' => $keyData['access_token'],
                'data' => [
                    'type' => 'users',
                    'id' => '<toString(@user->id)>',
                    'attributes' => [
                        'firstName' => 'second_request'
                    ]
                ]
            ]
        );
        $this->assertResponseContains(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '<toString(@user->id)>',
                    'attributes' => [
                        'firstName' => 'second_request'
                    ]
                ]
            ],
            $response
        );
        self::assertArrayNotHasKey('access_token', self::jsonToArray($response->getContent()));

        // test that the same access token can be used in header
        $response = $this->patch(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [
                'data' => [
                    'type' => 'users',
                    'id' => '<toString(@user->id)>',
                    'attributes' => [
                        'firstName' => 'third_request'
                    ]
                ]
            ],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $keyData['access_token']]
        );
        $this->assertResponseContains(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '<toString(@user->id)>',
                    'attributes' => [
                        'firstName' => 'third_request'
                    ]
                ]
            ],
            $response
        );
        self::assertArrayNotHasKey('access_token', self::jsonToArray($response->getContent()));
    }

    public function testApiRequestWithWrongAccessTokenShouldReturnUnauthorizedStatusCode(): void
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

    public function testApiRequestWithCorrectAccessTokenButForDeactivatedClientShouldReturnUnauthorizedStatus(): void
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

    public function testOptionsRequest(): void
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
    public function testOptionsPreflightRequest(string $requestMethod): void
    {
        $response = $this->sendRequest(
            'OPTIONS',
            $this->getUrl('oro_oauth2_server_auth_token_options'),
            [],
            [
                'HTTP_Origin' => 'https://oauth.test.com',
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

    public function testOptionsAsCorsButNotPreflightRequest(): void
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
