<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadPasswordGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class PasswordGrantOAuthServerTest extends OAuthServerTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadPasswordGrantClient::class, LoadUser::class]);
    }

    /**
     * @return string
     */
    private function getBearerAuthHeaderValue(): string
    {
        /** @var User $user */
        $user = $this->getReference('user');
        $userName = $user->getUsername();
        $responseData = $this->sendPasswordAccessTokenRequest($userName, $userName);

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    public function testGetAuthToken()
    {
        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $user = $this->getReference('user');
        $accessToken = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());

        self::assertEquals('Bearer', $accessToken['token_type']);
        self::assertLessThanOrEqual(3600, $accessToken['expires_in']);
        self::assertGreaterThanOrEqual(3599, $accessToken['expires_in']);
        self::assertArrayHasKey('refresh_token', $accessToken);

        [$accessTokenFirstPart, $accessTokenSecondPart] = explode('.', $accessToken['access_token']);
        $accessTokenFirstPart = self::jsonToArray(base64_decode($accessTokenFirstPart));
        $accessTokenSecondPart = self::jsonToArray(base64_decode($accessTokenSecondPart));
        self::assertEquals('JWT', $accessTokenFirstPart['typ']);
        self::assertEquals('RS256', $accessTokenFirstPart['alg']);
        self::assertEquals(LoadPasswordGrantClient::OAUTH_CLIENT_ID, $accessTokenSecondPart['aud']);

        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $client);
    }

    public function testLockUserOnAttemptsLimit()
    {
        if (!class_exists('Oro\Bundle\UserProBundle\OroUserProBundle')) {
            self::markTestSkipped('Could be tested only with UserPro bundle');
        }

        $configManager = $this->getConfigManager();
        $configManager->set('oro_user_pro.failed_login_limit_enabled', true);
        $configManager->set('oro_user_pro.failed_login_limit', 2);
        $configManager->flush();

        $user = $this->getReference('user');
        $result = $this->sendPasswordAccessTokenRequest($user->getUsername(), 'wrong', Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'error_description' => 'The user credentials were incorrect.',
                'message'           => 'The user credentials were incorrect.'
            ],
            $result
        );

        $result = $this->sendPasswordAccessTokenRequest($user->getUsername(), 'wrong', Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'error_description' => 'The user credentials were incorrect.',
                'message'           => 'The user credentials were incorrect.'
            ],
            $result
        );

        $result = $this->sendPasswordAccessTokenRequest($user->getUsername(), 'wrong', Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'error_description' => 'Account is locked.',
                'message'           => 'Account is locked.'
            ],
            $result
        );

        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->find($user->getId());
        self::assertEquals('locked', $user->getAuthStatus()->getId());
        self::assertEquals(3, $user->getFailedLoginCount());
    }

    public function testResetFailedLoginCounters()
    {
        if (!class_exists('Oro\Bundle\UserProBundle\OroUserProBundle')) {
            self::markTestSkipped('Could be tested only with UserPro bundle');
        }

        $configManager = $this->getConfigManager();
        $configManager->set('oro_user_pro.failed_login_limit_enabled', true);
        $configManager->set('oro_user_pro.failed_login_limit', 2);
        $configManager->flush();

        $user = $this->getReference('user');
        $result = $this->sendPasswordAccessTokenRequest($user->getUsername(), 'wrong', Response::HTTP_UNAUTHORIZED);
        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'error_description' => 'The user credentials were incorrect.',
                'message'           => 'The user credentials were incorrect.'
            ],
            $result
        );
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->find($user->getId());
        self::assertEquals(1, $user->getFailedLoginCount());

        $result = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());
        self::assertEquals('Bearer', $result['token_type']);
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->find($user->getId());
        self::assertEquals(0, $user->getFailedLoginCount());
    }

    public function testGetAuthTokenForNotExistingUser()
    {
        $responseContent = $this->sendPasswordAccessTokenRequest('test', 'test', Response::HTTP_UNAUTHORIZED);

        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'message'           => 'The user credentials were incorrect.',
                'error_description' => 'The user credentials were incorrect.'
            ],
            $responseContent
        );

        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertNull($client->getLastUsedAt());
    }

    public function testGetAuthTokenForDeactivatedUserShouldReturnUnauthorizedStatusCode()
    {
        /** @var User $user */
        $user = $this->getReference('user');
        $user->setEnabled(false);
        $this->getEntityManager()->flush();
        $userName = $user->getUsername();

        $responseContent = $this->sendPasswordAccessTokenRequest($userName, $userName, Response::HTTP_UNAUTHORIZED);

        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'message'           => 'Account is locked.',
                'error_description' => 'Account is locked.'
            ],
            $responseContent
        );
        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        self::assertNull($client->getLastUsedAt());
    }

    public function testGetAuthTokenForDeactivatedClientShouldReturnUnauthorizedStatusCode()
    {
        /** @var User $user */
        $user = $this->getReference('user');
        $userName = $user->getUsername();

        /** @var Client $client */
        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $responseContent = $this->sendPasswordAccessTokenRequest($userName, $userName, Response::HTTP_UNAUTHORIZED);

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'message'           => 'Client authentication failed',
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

    public function testApiRequestWithCorrectAccessTokenButForDeactivatedUserShouldReturnUnauthorizedStatusCode()
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue();

        /** @var User $user */
        $user = $this->getReference('user');
        $user->setEnabled(false);
        $this->getEntityManager()->flush();

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader],
            false
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testGetRefreshedAuthToken()
    {
        $user = $this->getReference('user');
        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);

        $accessToken = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());
        $passwordAccessDate = clone $client->getLastUsedAt();

        $startDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $refreshedToken = $this->sendRefreshAccessTokenRequest($accessToken['refresh_token']);

        self::assertNotEquals($accessToken['access_token'], $refreshedToken['access_token']);
        self::assertNotEquals($accessToken['refresh_token'], $refreshedToken['refresh_token']);

        $updatedClient = static::getContainer()->get('doctrine')
            ->getRepository(Client::class)
            ->findOneBy(['identifier' => LoadPasswordGrantClient::OAUTH_CLIENT_ID]);
        self::assertClientLastUsedValueIsCorrect($startDateTime, $updatedClient);
        self::assertTrue($passwordAccessDate < $updatedClient->getLastUsedAt());
    }

    public function testGetRefreshedAuthTokenForWrongRefreshToken()
    {
        $responseContent = $this->sendRefreshAccessTokenRequest('test', Response::HTTP_UNAUTHORIZED);

        self::assertEquals(
            [
                'error'             => 'invalid_request',
                'message'           => 'The refresh token is invalid.',
                'error_description' => 'The refresh token is invalid.',
                'hint'              => 'Cannot decrypt the refresh token'
            ],
            $responseContent
        );
    }

    public function testApiRequestWithCorrectRefreshedAccessTokenShouldReturnRequestedData()
    {
        $user = $this->getReference('user');
        $accessToken = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());
        $refreshedToken = $this->sendRefreshAccessTokenRequest($accessToken['refresh_token']);
        $refreshedAuthorizationHeader = sprintf('Bearer %s', $refreshedToken['access_token']);

        $expectedData = [
            'data' => [
                'type' => 'users',
                'id'   => '<toString(@user->id)>'
            ]
        ];

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $refreshedAuthorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);

        // test that the same access token can be used several times (until it will not be expired)
        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $refreshedAuthorizationHeader]
        );
        $this->assertResponseContains($expectedData, $response);
    }

    public function testApiRequestWithAccessTokedAfterGettingRefreshedAccessTokenShouldNotReturnData()
    {
        $user = $this->getReference('user');
        $accessToken = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());
        $refreshedToken = $this->sendRefreshAccessTokenRequest($accessToken['refresh_token']);

        $authorizationHeader = sprintf('Bearer %s', $accessToken['access_token']);
        $refreshedAuthorizationHeader = sprintf('Bearer %s', $refreshedToken['access_token']);

        // test that the old access token cannot be used
        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $authorizationHeader],
            false
        );
        $this->assertResponseStatusCodeEquals($response, Response::HTTP_UNAUTHORIZED);

        // test that refreshed access token can be used to get data
        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => $refreshedAuthorizationHeader]
        );
        $this->assertResponseContains(
            ['data' => ['type' => 'users', 'id' => '<toString(@user->id)>']],
            $response
        );
    }

    public function testGetRefreshedAuthTokenForDeactivatedUserShouldReturnUnauthorizedStatusCode()
    {
        $user = $this->getReference('user');
        $accessToken = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());

        $user->setEnabled(false);
        $this->getEntityManager()->flush();

        $refreshedToken = $this->sendRefreshAccessTokenRequest(
            $accessToken['refresh_token'],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_credentials',
                'message'           => 'Account is locked.',
                'error_description' => 'Account is locked.'
            ],
            $refreshedToken
        );
    }

    public function testGetRefreshedAuthTokenForDeactivatedClient()
    {
        /** @var User $user */
        $user = $this->getReference('user');
        $accessToken = $this->sendPasswordAccessTokenRequest($user->getUsername(), $user->getUsername());

        /** @var Client $client */
        $client = $this->getReference(LoadPasswordGrantClient::OAUTH_CLIENT_REFERENCE);
        $client->setActive(false);
        $this->getEntityManager()->flush();

        $refreshedToken = $this->sendRefreshAccessTokenRequest(
            $accessToken['refresh_token'],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'message'           => 'Client authentication failed',
                'error_description' => 'Client authentication failed'
            ],
            $refreshedToken
        );
    }

    /**
     * @param string $userName
     * @param string $password
     * @param int    $expectedStatusCode
     *
     * @return array
     */
    private function sendPasswordAccessTokenRequest(
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

    /**
     * @param string $token
     * @param int    $expectedStatusCode
     *
     * @return array
     */
    private function sendRefreshAccessTokenRequest(string $token, int $expectedStatusCode = Response::HTTP_OK): array
    {
        return $this->sendTokenRequest(
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'refresh_token' => $token
            ],
            $expectedStatusCode
        );
    }
}
