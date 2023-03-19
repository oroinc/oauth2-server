<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ConfigBundle\Tests\Functional\Traits\ConfigManagerAwareTestTrait;
use Oro\Bundle\CustomerBundle\Security\Token\AnonymousCustomerUserToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendPasswordGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadPasswordGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

class FrontendPasswordGrantVisitorOAuthServerTest extends OAuthServerTestCase
{
    use ConfigManagerAwareTestTrait;

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

    /**
     * {@inheritdoc}
     */
    protected function getListRouteName(): string
    {
        return 'oro_frontend_rest_api_list';
    }

    private function sendFrontendPasswordAccessTokenRequest(
        string $userName = 'guest',
        string $password = 'guest',
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

    public function testGetAuthToken()
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);
        self::assertArrayHasKey('access_token', $accessToken);
    }

    public function testTryToGetGetAuthTokenForBackendApplication()
    {
        $responseContent = $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username'      => 'guest',
                'password'      => 'guest'
            ],
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

    public function testGetRefreshedTokenByVisitorRefreshToken()
    {
        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

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

    public function testShoppingListWithGuestOauthToken()
    {
        if (!class_exists('Oro\Bundle\ShoppingListBundle\OroShoppingListBundle')) {
            self::markTestSkipped('Could be tested only with ShoppingList bundle');
        }

        $accessToken = $this->sendFrontendPasswordAccessTokenRequest();

        $configManager = self::getConfigManager();
        $configManager->set('oro_shopping_list.availability_for_guests', true);
        $configManager->flush();

        // assert that visitor has no shoppingLists
        $response = $this->cget(
            ['entity' => 'shoppinglists'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        $this->assertResponseContains(['data' => []], $response);

        // assert the visitor security token has correct roles
        $token = self::getContainer()->get('security.token_storage')->getToken();
        self::assertInstanceOf(AnonymousCustomerUserToken::class, $token);
        self::assertEquals(
            [self::getContainer()->get('oro_website.manager')->getCurrentWebsite()->getGuestRole()->getRole()],
            $token->getRoleNames()
        );

        // create one list
        $response = $this->post(
            ['entity' => 'shoppinglists'],
            'create_shopping_list.yml',
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        $responseContent = $this->updateResponseContent('create_shopping_list.yml', $response);
        $this->assertResponseContains($responseContent, $response);

        // assert that visitor has list
        $response = $this->cget(
            ['entity' => 'shoppinglists'],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken['access_token'])]
        );
        $responseContent = $this->updateResponseContent('cget_shopping_list.yml', $response);
        $this->assertResponseContains($responseContent, $response);

        //refresh the token
        $newAccessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
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
    }
}
