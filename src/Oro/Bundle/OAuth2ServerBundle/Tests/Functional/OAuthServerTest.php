<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiTestCase;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadOAuthClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class OAuthServerTest extends RestJsonApiTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadOAuthClient::class]);
    }

    /**
     * {@inheritdoc}
     */
    protected function checkWsseAuthHeader(array &$server)
    {
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param array  $server
     *
     * @return Response
     */
    private function sendRequest(string $method, string $uri, array $parameters = [], array $server = []): Response
    {
        $this->client->request($method, $uri, $parameters, [], $server);
        self::assertSessionNotStarted($method, $uri);

        return $this->client->getResponse();
    }

    /**
     * @param int $expectedStatusCode
     *
     * @return array
     */
    private function sendAccessTokenRequest(int $expectedStatusCode = Response::HTTP_OK): array
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadOAuthClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadOAuthClient::OAUTH_CLIENT_SECRET
            ]
        );

        self::assertResponseStatusCodeEquals($response, $expectedStatusCode);
        if (Response::HTTP_OK === $expectedStatusCode) {
            self::assertResponseContentTypeEquals($response, 'application/json; charset=UTF-8');
        } elseif ($expectedStatusCode >= Response::HTTP_BAD_REQUEST) {
            self::assertResponseContentTypeEquals($response, 'application/json');
        }

        return self::jsonToArray($response->getContent());
    }

    /**
     * @return string
     */
    private function getBearerAuthHeaderValue(): string
    {
        $responseData = $this->sendAccessTokenRequest();

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    public function testGetAuthToken()
    {
        $accessToken = $this->sendAccessTokenRequest();

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        list($accessTokenFirstPart, $accessTokenSecondPart) = explode('.', $accessToken['access_token']);
        $accessTokenFirstPart = self::jsonToArray(base64_decode($accessTokenFirstPart));
        $accessTokenSecondPart = self::jsonToArray(base64_decode($accessTokenSecondPart));
        self::assertEquals('JWT', $accessTokenFirstPart['typ']);
        self::assertEquals('RS256', $accessTokenFirstPart['alg']);
        self::assertEquals(LoadOAuthClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);
    }

    public function testGetAuthTokenForDeactivatedClient()
    {
        /** @var Client $client */
        $client = $this->getReference(LoadOAuthClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $responseContent = $this->sendAccessTokenRequest(Response::HTTP_UNAUTHORIZED);

        self::assertEquals(
            [
                'error' => 'invalid_client',
                'message' => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $responseContent
        );
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
    }

    public function testApiRequestWithCorrectAccessTokenButForDeactivatedClientShouldReturnUnauthorizedStatusCode()
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();

        /** @var Client $client */
        $client = $this->getReference(LoadOAuthClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
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

    public function methodsProvider()
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
    public function testOptionsPreflightRequest($requestMethod)
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
        self::assertResponseHeader($response, 'Access-Control-Allow-Headers', 'Content-Type');
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
