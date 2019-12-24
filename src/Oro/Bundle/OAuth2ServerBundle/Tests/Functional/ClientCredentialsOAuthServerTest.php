<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class ClientCredentialsOAuthServerTest extends OAuthServerTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class]);
    }

    /**
     * @param int $expectedStatusCode
     *
     * @return array
     */
    private function sendAccessTokenRequest(int $expectedStatusCode = Response::HTTP_OK): array
    {
        return $this->sendTokenRequest(
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
            ],
            $expectedStatusCode
        );
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
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $accessToken = $this->sendAccessTokenRequest();

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        list($accessTokenFirstPart, $accessTokenSecondPart) = explode('.', $accessToken['access_token']);
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

        $responseContent = $this->sendAccessTokenRequest(Response::HTTP_UNAUTHORIZED);

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
