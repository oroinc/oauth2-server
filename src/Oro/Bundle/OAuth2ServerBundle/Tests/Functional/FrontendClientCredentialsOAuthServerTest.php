<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendClientCredentialsClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

class FrontendClientCredentialsOAuthServerTest extends OAuthServerTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }

        $this->initClient();
        $this->loadFixtures([
            LoadClientCredentialsClient::class,
            LoadFrontendClientCredentialsClient::class,
            LoadUser::class,
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    private function sendAccessTokenRequest(
        string $clientId,
        string $clientSecret,
        int $expectedStatusCode = Response::HTTP_OK,
        array $server = []
    ): Response {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret
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

    private function getBearerAuthHeaderValue(string $clientId, string $clientSecret): string
    {
        $response = $this->sendAccessTokenRequest($clientId, $clientSecret);
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseData = self::jsonToArray($response->getContent());

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    private function frontendGet(
        array $routeParameters,
        array $parameters,
        array $server,
        bool $assertValid = true
    ): Response {
        $server['HTTP_ACCEPT'] = self::JSON_API_MEDIA_TYPE;
        $this->client->request(
            'GET',
            $this->getUrl('oro_frontend_rest_api_item', $routeParameters),
            $parameters,
            [],
            $server
        );

        $response = $this->client->getResponse();

        if ($assertValid) {
            $entityType = $routeParameters['entity'];
            self::assertApiResponseStatusCodeEquals(
                $response,
                Response::HTTP_OK,
                $entityType,
                'get'
            );
            self::assertResponseContentTypeEquals($response, $this->getResponseContentType());
        }

        return $response;
    }

    public function testMakeApiFrontendRequestWithFrontendAccessToken(): void
    {
        $expectedData = [
            'data' => [
                'type' => 'customerusers',
                'id'   => '<toString(@grzegorz.brzeczyszczykiewicz@example.com->id)>'
            ]
        ];

        $frontendAuthorizationHeader = $this->getBearerAuthHeaderValue(
            LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
            LoadFrontendClientCredentialsClient::OAUTH_CLIENT_SECRET
        );

        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();
        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $frontendAuthorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
    }

    public function testTryToMakeApiFrontendRequestWithBackendAccessTokenShouldReturnUnauthorizedStatusCode(): void
    {
        $backendAuthorizationHeader = $this->getBearerAuthHeaderValue(
            LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
        );

        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();
        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $backendAuthorizationHeader],
            false
        );
        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
        self::assertResponseHeader(
            $response,
            'WWW-Authenticate',
            'WSSE realm="Secured Frontend API", profile="UsernameToken"'
        );
    }

    public function testMakeApiBackendRequestWithBackendAccessToken(): void
    {
        $expectedData = [
            'data' => [
                'type' => 'users',
                'id'   => '<toString(@user->id)>'
            ]
        ];

        $backendAuthorizationHeader = $this->getBearerAuthHeaderValue(
            LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
        );

        $userId = $this->getReference('user')->getId();
        $response = $this->get(
            ['entity' => 'users', 'id' => $userId],
            [],
            ['HTTP_AUTHORIZATION' => $backendAuthorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
    }

    public function testTryToMakeApiBackendRequestWithFrontendAccessTokenShouldReturnUnauthorizedStatusCode(): void
    {
        $frontendAuthorizationHeader = $this->getBearerAuthHeaderValue(
            LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
            LoadFrontendClientCredentialsClient::OAUTH_CLIENT_SECRET
        );

        $customerUserId = $this->getReference('user')->getId();
        $response = $this->get(
            ['entity' => 'users', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $frontendAuthorizationHeader],
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
}
