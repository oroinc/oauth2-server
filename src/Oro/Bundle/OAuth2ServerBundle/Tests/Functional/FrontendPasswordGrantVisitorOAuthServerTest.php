<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ConfigBundle\Tests\Functional\Traits\ConfigManagerAwareTestTrait;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendPasswordGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadPasswordGrantClient;
use Symfony\Component\HttpFoundation\Response;

class FrontendPasswordGrantVisitorOAuthServerTest extends OAuthServerTestCase
{
    use ConfigManagerAwareTestTrait;

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
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    #[\Override]
    protected function getListRouteName(): string
    {
        return 'oro_frontend_rest_api_list';
    }

    private function sendFrontendPasswordAccessTokenRequest(
        string $userName = 'guest',
        string $password = 'guest',
        int $expectedStatusCode = Response::HTTP_OK,
        array $additionalRequestData = []
    ): array {
        return $this->sendTokenRequest(
            array_merge([
                'grant_type' => 'password',
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username' => $userName,
                'password' => $password
            ], $additionalRequestData),
            $expectedStatusCode
        );
    }

    public function testGetAuthToken(): void
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);
        self::assertArrayHasKey('access_token', $accessToken);
    }

    public function testTryToGetGetVisitorAuthTokenForBackendApplication(): void
    {
        $responseContent = $this->sendTokenRequest(
            [
                'grant_type' => 'password',
                'client_id' => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username' => 'guest',
                'password' => 'guest'
            ],
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

    public function testGetRefreshedTokenByVisitorRefreshToken(): void
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

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

    public function testShoppingListWithGuestOauthTokenWhenShoppingListsForGuestsAreDisabled(): void
    {
        if (!class_exists('Oro\Bundle\ShoppingListBundle\OroShoppingListBundle')) {
            self::markTestSkipped('can be tested only with ShoppingListBundle');
        }

        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

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

        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

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
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
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
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest(
            'grzegorz.brzeczyszczykiewicz@example.com',
            'test',
            Response::HTTP_OK,
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
}
