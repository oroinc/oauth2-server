<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData;
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
    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->initClient();
        $this->loadFixtures([
            LoadPasswordGrantClient::class,
            LoadFrontendPasswordGrantClient::class,
            LoadUser::class,
            LoadCustomerUserData::class
        ]);
    }

    private function setFrontendClientOwner(string $ownerReference): void
    {
        /** @var CustomerUser $owner */
        $owner = $this->getReference($ownerReference);
        $client = $this->getReference(LoadFrontendPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        $client->setOwnerEntity(CustomerUser::class, $owner->getId());
        $this->getEntityManager()->flush();
    }

    private function setBackendClientOwner(): void
    {
        /** @var User $owner */
        $owner = $this->getReference(LoadUser::USER);
        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        $client->setOwnerEntity(User::class, $owner->getId());
        $this->getEntityManager()->flush();
    }

    private function getBackendBearerAuthHeaderValue(): string
    {
        /** @var User $user */
        $user = $this->getReference(LoadUser::USER);
        $userName = $user->getUserIdentifier();
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
                'grant_type' => 'password',
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username' => $userName,
                'password' => $password
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
                'grant_type' => 'password',
                'client_id' => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username' => $userName,
                'password' => $password
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
     */
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

    public function testFrontendGetAuthTokenWithFrontendCredentialsShouldReturnAccessAndRefreshTokens(): void
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

    /**
     * @dataProvider frontendOwnerScopeDataProvider
     */
    public function testGetFrontendAuthTokenRespectsClientOwnerScope(
        string $userName,
        string $password,
        int $expectedStatusCode,
        bool $tokenExpected
    ): void {
        $this->setFrontendClientOwner(LoadCustomerUserData::EMAIL);

        $responseContent = $this->sendFrontendPasswordAccessTokenRequest(
            $userName,
            $password,
            $expectedStatusCode
        );

        if ($tokenExpected) {
            self::assertArrayHasKey('access_token', $responseContent);
        } else {
            self::assertArrayNotHasKey('access_token', $responseContent);
            self::assertSame('invalid_grant', $responseContent['error']);
        }
    }

    public static function frontendOwnerScopeDataProvider(): array
    {
        return [
            'user from the owner customer' => [
                LoadCustomerUserData::LEVEL_1_EMAIL,
                LoadCustomerUserData::LEVEL_1_PASSWORD,
                Response::HTTP_OK,
                true
            ],
            'user from a subordinate customer' => [
                LoadCustomerUserData::LEVEL_1_1_EMAIL,
                LoadCustomerUserData::LEVEL_1_1_PASSWORD,
                Response::HTTP_OK,
                true
            ],
            'user from another customer tree' => [
                LoadCustomerUserData::ORPHAN_EMAIL,
                LoadCustomerUserData::ORPHAN_PASSWORD,
                Response::HTTP_BAD_REQUEST,
                false
            ]
        ];
    }

    public function testApiFrontendRequestWithinClientOwnerScopeShouldReturnRequestedData(): void
    {
        $this->setFrontendClientOwner(LoadCustomerUserData::EMAIL);
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            LoadCustomerUserData::LEVEL_1_1_EMAIL,
            LoadCustomerUserData::LEVEL_1_1_PASSWORD
        );
        /** @var CustomerUser $customerUser */
        $customerUser = $this->getReference(LoadCustomerUserData::LEVEL_1_1_EMAIL);

        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUser->getId()],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );

        $this->assertResponseContains(
            ['data' => ['type' => 'customerusers', 'id' => (string)$customerUser->getId()]],
            $response
        );
    }

    public function testApiFrontendRequestOutsideCurrentClientOwnerScopeShouldReturnUnauthorized(): void
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            LoadCustomerUserData::ORPHAN_EMAIL,
            LoadCustomerUserData::ORPHAN_PASSWORD
        );
        $this->setFrontendClientOwner(LoadCustomerUserData::EMAIL);
        /** @var CustomerUser $customerUser */
        $customerUser = $this->getReference(LoadCustomerUserData::ORPHAN_EMAIL);

        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUser->getId()],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
    }

    public function testBackendClientOwnerDoesNotEnableFrontendOwnerScopeValidation(): void
    {
        $this->setBackendClientOwner();
        $authorizationHeader = $this->getBackendBearerAuthHeaderValue();

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader]
        );

        $this->assertResponseContains(
            ['data' => ['type' => 'users', 'id' => '<toString(@user->id)>']],
            $response
        );
    }

    public function testFrontendGetAuthTokenWithBackendCredentialsShouldReturnBadRequestStatusCode(): void
    {
        $user = $this->getReference(LoadUser::USER);
        $responseContent = $this->sendFrontendPasswordAccessTokenRequest(
            $user->getUserIdentifier(),
            $user->getUserIdentifier(),
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals(
            [
                'error' => 'invalid_grant',
                'message' => 'The user credentials were incorrect.',
                'error_description' => 'The user credentials were incorrect.'
            ],
            $responseContent
        );
    }

    public function testBackendGetAuthTokenWithFrontendCredentialsShouldReturnBadRequestStatusCode(): void
    {
        $responseContent = $this->sendBackendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test',
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals(
            [
                'error' => 'invalid_grant',
                'message' => 'The user credentials were incorrect.',
                'error_description' => 'The user credentials were incorrect.'
            ],
            $responseContent
        );
    }

    public function testGetFrontendAuthTokenForDeactivatedCustomerUserShouldReturnUnauthorizedStatusCode(): void
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
                'error' => 'invalid_grant',
                'message' => 'The provided authorization grant (e.g., authorization code,'
                    . ' resource owner credentials) or refresh token is invalid, expired, revoked,'
                    . ' does not match the redirection URI used in the authorization request,'
                    . ' or was issued to another client.',
                'error_description' => 'The provided authorization grant (e.g., authorization code,'
                    . ' resource owner credentials) or refresh token is invalid, expired, revoked,'
                    . ' does not match the redirection URI used in the authorization request,'
                    . ' or was issued to another client.',
                'hint' => 'Account is disabled.'
            ],
            $responseContent
        );
    }

    public function testApiFrontendRequestWithCorrectAccessTokenShouldReturnRequestedData(): void
    {
        $authorizationHeader = $this->getFrontendBearerAuthHeaderValue();
        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();

        $expectedData = [
            'data' => [
                'type' => 'customerusers',
                'id' => (string)$customerUserId
            ]
        ];

        $response = $this->frontendGet(
            ['entity' => 'customerusers', 'id' => $customerUserId],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
    }

    public function testApiFrontendRequestWithBackendAccessTokenShouldReturnUnauthorizedStatusCode(): void
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
    }

    public function testApiBackendRequestWithFrontendAccessTokenShouldReturnUnauthorizedStatusCode(): void
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
    }

    public function testGetFrontendRefreshedTokenByFrontendRefreshToken(): void
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );

        $refreshedToken = $this->sendTokenRequest(
            [
                'grant_type' => 'refresh_token',
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ]
        );

        self::assertNotEquals($accessToken['access_token'], $refreshedToken['access_token']);
        self::assertNotEquals($accessToken['refresh_token'], $refreshedToken['refresh_token']);
    }

    public function testGetFrontendRefreshedTokenByBackendRefreshTokenShouldReturnUnauthorizedStatusCode(): void
    {
        /** @var User $user */
        $user = $this->getReference(LoadUser::USER);
        $userName = $user->getUserIdentifier();
        $accessToken = $this->sendBackendPasswordAccessTokenRequest($userName, $userName);

        $responseContent = $this->sendTokenRequest(
            [
                'grant_type' => 'refresh_token',
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error' => 'invalid_request',
                'error_description' => 'The refresh token is invalid.',
                'hint' => 'Token is not linked to client',
                'message' => 'The refresh token is invalid.'
            ],
            $responseContent
        );
    }

    public function testGetBackendRefreshedTokenByFronendRefreshTokenShouldReturnUnauthorizedStatusCode(): void
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );

        $responseContent = $this->sendTokenRequest(
            [
                'grant_type' => 'refresh_token',
                'client_id' => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error' => 'invalid_request',
                'error_description' => 'The refresh token is invalid.',
                'hint' => 'Token is not linked to client',
                'message' => 'The refresh token is invalid.'
            ],
            $responseContent
        );
    }

    public function testApiFrontendRequestWithCorrectRefreshedAccessTokenShouldReturnRequestedData(): void
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test'
        );
        $refreshedToken = $this->sendTokenRequest(
            [
                'grant_type' => 'refresh_token',
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ]
        );

        $refreshedAuthorizationHeader = sprintf('Bearer %s', $refreshedToken['access_token']);
        $customerUserId = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com')->getId();
        $expectedData = [
            'data' => [
                'type' => 'customerusers',
                'id' => (string)$customerUserId
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
