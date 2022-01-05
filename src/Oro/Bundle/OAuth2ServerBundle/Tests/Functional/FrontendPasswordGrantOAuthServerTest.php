<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendPasswordGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadPasswordGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FrontendPasswordGrantOAuthServerTest extends OAuthServerTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }

        $this->initClient();
        $this->loadFixtures([
            LoadPasswordGrantClient::class,
            LoadFrontendPasswordGrantClient::class,
            LoadUser::class,
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    private function getBackendBearerAuthHeaderValue(): string
    {
        /** @var User $user */
        $user = $this->getReference('user');
        $userName = $user->getUsername();
        $responseData = $this->sendBackendPasswordAccessTokenRequest($userName, $userName);

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    private function sendFrontendPasswordAccessTokenRequest(
        string $userName,
        string $password,
        int $expectedStatusCode = Response::HTTP_OK
    ): array {
        return $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username'      => $userName,
                'password'      => $password
            ],
            $expectedStatusCode
        );
    }

    private function sendBackendPasswordAccessTokenRequest(
        string $userName,
        string $password,
        int $expectedStatusCode = Response::HTTP_OK
    ): array {
        return $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username'      => $userName,
                'password'      => $password
            ],
            $expectedStatusCode
        );
    }

    private function getFrontendBearerAuthHeaderValue(): string
    {
        $responseData = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    /**
     * Sends GET request for a single entity to Frontend API.
     *
     * @param array $routeParameters
     * @param array $parameters
     * @param array $server
     * @param bool  $assertValid
     *
     * @return Response
     */
    private function frontendGet(
        array $routeParameters,
        array $parameters,
        array $server,
        $assertValid = true
    ) {
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

    public function testFrontendGetAuthTokenWithFrontendCredentialsShouldReturnAccessAndRefreshTokens()
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);
        self::assertArrayHasKey('access_token', $accessToken);

        $client = $this->getReference(LoadFrontendPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testFrontendGetAuthTokenWithBackendCredentialsShouldReturnBadRequestStatusCode()
    {
        $user = $this->getReference('user');
        $responseContent = $this->sendFrontendPasswordAccessTokenRequest(
            $user->getUsername(),
            $user->getUsername(),
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals(
            [
                'error'             => 'invalid_grant',
                'message'           => 'The user credentials were incorrect.',
                'error_description' => 'The user credentials were incorrect.'
            ],
            $responseContent
        );
    }

    public function testBackendGetAuthTokenWithFrontendCredentialsShouldReturnBadRequestStatusCode()
    {
        $responseContent = $this->sendBackendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test',
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals(
            [
                'error'             => 'invalid_grant',
                'message'           => 'The user credentials were incorrect.',
                'error_description' => 'The user credentials were incorrect.'
            ],
            $responseContent
        );
    }

    public function testGetFrontendAuthTokenForDeactivatedCustomerUserShouldReturnUnauthorizedStatusCode()
    {
        $user = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com');
        $user->setEnabled(false);
        $this->getEntityManager()->flush();

        $responseContent = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test',
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals(
            [
                'error'             => 'invalid_grant',
                'message'           => 'The provided authorization grant (e.g., authorization code,'
                    . ' resource owner credentials) or refresh token is invalid, expired, revoked,'
                    . ' does not match the redirection URI used in the authorization request,'
                    . ' or was issued to another client.',
                'error_description' => 'The provided authorization grant (e.g., authorization code,'
                    . ' resource owner credentials) or refresh token is invalid, expired, revoked,'
                    . ' does not match the redirection URI used in the authorization request,'
                    . ' or was issued to another client.',
                'hint'              => 'Account is disabled.'
            ],
            $responseContent
        );
    }

    public function testApiFrontendRequestWithCorrectAccessTokenShouldReturnRequestedData()
    {
        $authorizationHeader = $this->getFrontendBearerAuthHeaderValue();
        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();

        $expectedData = [
            'data' => [
                'type' => 'customerusers',
                'id'   => (string)$customerUserId
            ]
        ];

        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
    }

    public function testApiFrontendRequestWithBackendAccessTokenShouldReturnUnauthorizedStatusCode()
    {
        $authorizationHeader = $this->getBackendBearerAuthHeaderValue();
        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();

        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader],
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

    public function testApiBackendRequestWithFrontendAccessTokenShouldReturnUnauthorizedStatusCode()
    {
        $authorizationHeader = $this->getFrontendBearerAuthHeaderValue();

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

    public function testGetFrontendRefreshedTokenByFrontendRefreshToken()
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );

        $refreshedToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ]
        );

        self::assertNotEquals($accessToken['access_token'], $refreshedToken['access_token']);
        self::assertNotEquals($accessToken['refresh_token'], $refreshedToken['refresh_token']);
    }

    public function testGetFrontendRefreshedTokenByBackendRefreshTokenShouldReturnUnauthorizedStatusCode()
    {
        /** @var User $user */
        $user = $this->getReference('user');
        $userName = $user->getUsername();
        $accessToken = $this->sendBackendPasswordAccessTokenRequest($userName, $userName);

        $responseContent = $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_request',
                'error_description' => 'The refresh token is invalid.',
                'hint'              => 'Token is not linked to client',
                'message'           => 'The refresh token is invalid.'
            ],
            $responseContent
        );
    }

    public function testGetBackendRefreshedTokenByFronendRefreshTokenShouldReturnUnauthorizedStatusCode()
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );

        $responseContent = $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_request',
                'error_description' => 'The refresh token is invalid.',
                'hint'              => 'Token is not linked to client',
                'message'           => 'The refresh token is invalid.'
            ],
            $responseContent
        );
    }

    public function testApiFrontendRequestWithCorrectRefreshedAccessTokenShouldReturnRequestedData()
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );
        $refreshedToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ]
        );

        $refreshedAuthorizationHeader = sprintf('Bearer %s', $refreshedToken['access_token']);
        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();
        $expectedData = [
            'data' => [
                'type' => 'customerusers',
                'id'   => (string)$customerUserId
            ]
        ];

        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $refreshedAuthorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
    }
}
