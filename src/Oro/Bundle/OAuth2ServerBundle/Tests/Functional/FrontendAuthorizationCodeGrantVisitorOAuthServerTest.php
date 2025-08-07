<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadAuthorizationCodeGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendAuthorizationCodeGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FrontendAuthorizationCodeGrantVisitorOAuthServerTest extends OAuthServerTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }
        $this->initClient();
        $this->loadFixtures([
            LoadAuthorizationCodeGrantClient::class,
            LoadFrontendAuthorizationCodeGrantClient::class,
            LoadUser::class,
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    #[\Override]
    protected function getListRouteName(): string
    {
        return 'oro_frontend_rest_api_list';
    }

    private function sendAuthCodeRequest(string $clientId, ?string $redirectUri = null): Response
    {
        $requestParameters = [
            'response_type' => 'code',
            'client_id' => $clientId
        ];
        if (null !== $redirectUri) {
            $requestParameters['redirect_uri'] = $redirectUri;
        }

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_frontend_authenticate_visitor', $requestParameters)
        );

        return $this->client->getResponse();
    }

    private function getAuthCode(string $clientId, string $redirectUri): string
    {
        $response = $this->sendAuthCodeRequest($clientId, $redirectUri);

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        return $parameters['code'];
    }

    private function sendAccessTokenRequest(
        ?string $code,
        $redirectUri,
        int $expectedStatusCode = Response::HTTP_OK
    ): array {
        if (null === $code) {
            $code = $this->getAuthCode(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, 'http://test.com');
        }

        return $this->sendTokenRequest(
            [
                'grant_type' => 'authorization_code',
                'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => $redirectUri
            ],
            $expectedStatusCode
        );
    }

    private function sendAccessTokenRequestForUser(
        string $userName,
        string $password,
        array $additionalRequestData = [],
        bool $sendViaPost = false
    ): array {
        $authenticateParameters = array_merge([
            'response_type' => 'code',
            'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'redirect_uri' => 'http://test.com'
        ], $additionalRequestData);
        $authenticateUrl = $this->getUrl('oro_oauth2_server_frontend_authenticate', $authenticateParameters);
        $this->client->request(
            $sendViaPost ? 'POST' : 'GET',
            $authenticateUrl,
            [],
            [],
            $sendViaPost ? ['HTTP_X-HTTP-Method-Override' => 'GET'] : []
        );
        $authenticateResponse = $this->client->getResponse();
        self::assertHtmlResponseStatusCodeEquals($authenticateResponse, Response::HTTP_UNAUTHORIZED);
        $session = $this->client->getRequest()->getSession();
        $storedTargetPath = $session->get('_security.frontend.target_path');
        foreach ($authenticateParameters as $name => $value) {
            self::assertStringContainsString($name . '=' . urlencode($value), $storedTargetPath, $name);
        }

        $this->initClient([], self::generateBasicAuthHeader($userName, $password));
        $this->client->request(
            'POST',
            $authenticateUrl,
            [
                'grantAccess' => 'true',
                '_csrf_token' => $this->getCsrfToken('authorize_client')->getValue()
            ]
        );
        $authCodeResponse = $this->client->getResponse();
        $authCodeRequestResult = [];
        self::assertResponseStatusCodeEquals($authCodeResponse, Response::HTTP_FOUND);
        $redirectUrl = $authCodeResponse->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $authCodeRequestResult);

        return $this->sendTokenRequest(
            array_merge([
                'grant_type' => 'authorization_code',
                'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'code' => $authCodeRequestResult['code'],
                'redirect_uri' => 'http://test.com'
            ], $additionalRequestData)
        );
    }

    public function testAuthenticateRequestWithInvalidHttpMethod(): void
    {
        $this->client->request(
            'POST',
            $this->getUrl(
                'oro_oauth2_server_frontend_authenticate_visitor',
                [
                    'response_type' => 'code',
                    'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                    'redirect_uri' => 'http://test.com'
                ]
            )
        );
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeEquals($response, Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testGetAuthCode(): void
    {
        $code = $this->getAuthCode(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, 'http://test.com');

        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testGetAuthCodeWithoutRedirectUri(): void
    {
        $response = $this->sendAuthCodeRequest(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID);

        $parameters = [];
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FOUND);
        $redirectUrl = $response->headers->get('location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        $code = $parameters['code'];
        self::assertIsString($code);
        self::assertNotNull($code);
    }

    public function testTryToGetAuthCodeWithWrongRedirectUri(): void
    {
        $response = $this->sendAuthCodeRequest(
            LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://wrong.com'
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testTryToGetAuthCodeWithWrongClientId(): void
    {
        $response = $this->sendAuthCodeRequest('wrong_client', 'http://test.com');

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            '{"error":"invalid_client","error_description":"Client authentication failed",'
            . '"message":"Client authentication failed"}',
            $response->getContent()
        );
        self::assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testTryToGetFrontendAuthCodeOnBackendClient(): void
    {
        $response = $this->sendAuthCodeRequest(
            LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
            'http://test.com'
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
    }

    public function testGetAuthToken(): void
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);

        [$accessTokenPart1, $accessTokenPart2] = explode('.', $accessToken['access_token']);
        $accessTokenPart1 = self::jsonToArray(base64_decode($accessTokenPart1));
        $accessTokenPart2 = self::jsonToArray(base64_decode($accessTokenPart2));
        self::assertEquals('JWT', $accessTokenPart1['typ']);
        self::assertEquals('RS256', $accessTokenPart1['alg']);
        self::assertEquals(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, $accessTokenPart2['aud']);
        self::assertStringStartsWith('visitor:', $accessTokenPart2['sub']);

        $client = $this->getReference(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);

        // check that a new access token is generated for another visitor
        $anotherAccessToken = $this->sendAccessTokenRequest(null, 'http://test.com');
        self::assertEquals('Bearer', $anotherAccessToken['token_type']);
        self::assertArrayHasKey('access_token', $anotherAccessToken);
        self::assertArrayHasKey('refresh_token', $anotherAccessToken);
        self::assertNotEquals($accessToken['access_token'], $anotherAccessToken['access_token']);
        self::assertNotEquals($accessToken['refresh_token'], $anotherAccessToken['refresh_token']);
        [$anotherAccessTokenPart1, $anotherAccessTokenPart2] = explode('.', $anotherAccessToken['access_token']);
        $anotherAccessTokenPart1 = self::jsonToArray(base64_decode($anotherAccessTokenPart1));
        $anotherAccessTokenPart2 = self::jsonToArray(base64_decode($anotherAccessTokenPart2));
        self::assertEquals('JWT', $anotherAccessTokenPart1['typ']);
        self::assertStringStartsWith('visitor:', $anotherAccessTokenPart2['sub']);
        self::assertNotEquals($accessTokenPart2['sub'], $anotherAccessTokenPart2['sub']);
    }

    public function testGetRefreshedTokenByVisitorRefreshToken(): void
    {
        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        $refreshedToken = $this->sendTokenRequest(
            [
                'grant_type' => 'refresh_token',
                'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ]
        );

        self::assertNotEquals($accessToken['access_token'], $refreshedToken['access_token']);
        self::assertNotEquals($accessToken['refresh_token'], $refreshedToken['refresh_token']);
        [$refreshedTokenPart1, $refreshedTokenPart2] = explode('.', $refreshedToken['access_token']);
        $refreshedTokenPart1 = self::jsonToArray(base64_decode($refreshedTokenPart1));
        $refreshedTokenPart2 = self::jsonToArray(base64_decode($refreshedTokenPart2));
        self::assertEquals('JWT', $refreshedTokenPart1['typ']);
        self::assertEquals(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, $refreshedTokenPart2['aud']);
        self::assertStringStartsWith('visitor:', $refreshedTokenPart2['sub']);
    }

    public function testTryToGetAuthTokenTwiceWithSameAuthCode(): void
    {
        $code = $this->getAuthCode(LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID, 'http://test.com');

        $accessToken = $this->sendAccessTokenRequest($code, 'http://test.com');
        $accessToken1 = $this->sendAccessTokenRequest($code, 'http://test.com', Response::HTTP_BAD_REQUEST);

        self::assertArrayHasKey('access_token', $accessToken);
        self::assertArrayNotHasKey('access_token', $accessToken1);
        self::assertEquals('Authorization code has been revoked', $accessToken1['hint']);
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

    public function testTryToGetAuthTokenWithWrongRedirectUri(): void
    {
        $accessToken = $this->sendAccessTokenRequest(
            null,
            'http://wrong.com',
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals('invalid_client', $accessToken['error']);
    }

    public function testTryToMakeApiRequestWithCorrectFrontendAccessTokenOnBackendApi(): void
    {
        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
        self::assertSame('', $response->getContent());
    }

    public function testShoppingListWithGuestOauthTokenWhenShoppingListsForGuestsAreDisabled(): void
    {
        if (!class_exists('Oro\Bundle\ShoppingListBundle\OroShoppingListBundle')) {
            self::markTestSkipped('can be tested only with ShoppingListBundle');
        }

        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        $response = $this->request(
            'GET',
            $this->getUrl($this->getListRouteName(), self::processTemplateData(['entity' => 'shoppinglists'])),
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FORBIDDEN);

        $response = $this->post(
            ['entity' => 'shoppinglists'],
            'create_shopping_list.yml',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])],
            false
        );
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FORBIDDEN);
    }

    public function testShoppingListWithGuestOauthToken(): void
    {
        if (!class_exists('Oro\Bundle\ShoppingListBundle\OroShoppingListBundle')) {
            self::markTestSkipped('can be tested only with ShoppingListBundle');
        }

        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        $configManager = self::getConfigManager();
        $configManager->set('oro_shopping_list.availability_for_guests', true);
        $configManager->flush();

        // assert that visitor has no shopping lists
        $response = $this->request(
            'GET',
            $this->getUrl($this->getListRouteName(), self::processTemplateData(['entity' => 'shoppinglists'])),
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
        $this->assertResponseContains(['data' => []], $response);

        // assert the visitor security token has correct roles
        $token = self::getContainer()->get('security.token_storage')->getToken();
        self::assertInstanceOf(AnonymousCustomerUserToken::class, $token);
        self::assertEquals(
            [self::getContainer()->get('oro_website.manager')->getCurrentWebsite()->getGuestRole()->getRole()],
            $token->getRoleNames()
        );

        // create a shopping list
        $response = $this->post(
            ['entity' => 'shoppinglists'],
            'create_shopping_list.yml',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        $responseContent = $this->updateResponseContent('create_shopping_list.yml', $response);
        $this->assertResponseContains($responseContent, $response);

        // assert that visitor has a shopping list
        $response = $this->cget(
            ['entity' => 'shoppinglists'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        $responseContent = $this->updateResponseContent('cget_shopping_list.yml', $response);
        $this->assertResponseContains($responseContent, $response);

        // refresh the token
        $newAccessToken = $this->sendTokenRequest(
            [
                'grant_type' => 'refresh_token',
                'client_id' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $accessToken['refresh_token']
            ]
        );
        $this->assertNotEquals($newAccessToken['access_token'], $accessToken['access_token']);

        // assert that we still work with the same visitor
        $response = $this->cget(
            ['entity' => 'shoppinglists'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $newAccessToken['access_token'])]
        );
        $responseContent = $this->updateResponseContent('cget_shopping_list.yml', $response);
        $this->assertResponseContains($responseContent, $response);

        // assert that the visitor shopping lists are copied to the customer user
        $accessToken = $this->sendAccessTokenRequestForUser(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test',
            ['visitor_access_token' => $newAccessToken['access_token']]
        );
        $response = $this->cget(
            ['entity' => 'shoppinglists'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        /** @var CustomerUser $customerUser */
        $customerUser = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com');
        $responseContent['data'][0]['relationships']['customerUser']['data'] = [
            'type' => 'customerusers',
            'id' => (string)$customerUser->getId()
        ];
        $responseContent['data'][0]['relationships']['customer']['data'] = [
            'type' => 'customers',
            'id' => (string)$customerUser->getCustomer()->getId()
        ];
        $this->assertResponseContains($responseContent, $response);

        $configManager->set('oro_shopping_list.availability_for_guests', false);
        $configManager->flush();
    }

    public function testShoppingListWithGuestOauthTokenWhenAccessTokenRequestForUserIsSentViaPost(): void
    {
        if (!class_exists('Oro\Bundle\ShoppingListBundle\OroShoppingListBundle')) {
            self::markTestSkipped('can be tested only with ShoppingListBundle');
        }

        $accessToken = $this->sendAccessTokenRequest(null, 'http://test.com');

        $configManager = self::getConfigManager();
        $configManager->set('oro_shopping_list.availability_for_guests', true);
        $configManager->flush();

        // create a shopping list
        $response = $this->post(
            ['entity' => 'shoppinglists'],
            'create_shopping_list.yml',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        $responseContent = $this->updateResponseContent('create_shopping_list.yml', $response);
        $this->assertResponseContains($responseContent, $response);

        // assert that the visitor shopping lists are copied to the customer user
        $accessToken = $this->sendAccessTokenRequestForUser(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test',
            ['visitor_access_token' => $accessToken['access_token']],
            true
        );
        $response = $this->cget(
            ['entity' => 'shoppinglists'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        /** @var CustomerUser $customerUser */
        $customerUser = $this->getReference('grzegorz.brzeczyszczykiewicz@example.com');
        $responseContent = $this->updateResponseContent('cget_shopping_list.yml', $response);
        $responseContent['data'][0]['relationships']['customerUser']['data'] = [
            'type' => 'customerusers',
            'id' => (string)$customerUser->getId()
        ];
        $responseContent['data'][0]['relationships']['customer']['data'] = [
            'type' => 'customers',
            'id' => (string)$customerUser->getCustomer()->getId()
        ];
        $this->assertResponseContains($responseContent, $response);

        $configManager->set('oro_shopping_list.availability_for_guests', false);
        $configManager->flush();
    }
}
