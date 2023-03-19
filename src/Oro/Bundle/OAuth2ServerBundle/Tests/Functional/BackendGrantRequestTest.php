<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadAuthorizationCodeGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadPasswordGrantClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

class BackendGrantRequestTest extends OAuthServerTestCase
{
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([
            LoadClientCredentialsClient::class,
            LoadPasswordGrantClient::class,
            LoadAuthorizationCodeGrantClient::class,
            LoadUser::class,
        ]);
    }

    private function getAuthCode(string $clientId): string
    {
        $this->initClient([], self::generateBasicAuthHeader());
        $requestParameters = [
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => 'http://test.com',
        ];

        $this->client->request(
            'POST',
            $this->getUrl(
                'oro_oauth2_server_authenticate',
                $requestParameters
            ),
            [
                'grantAccess' => 'true',
                '_csrf_token' => $this->getCsrfToken('authorize_client')->getValue(),
            ]
        );

        $response = $this->client->getResponse();
        $redirectUrl = $response->headers->get('location');
        $parameters = [];
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $parameters);

        return $parameters['code'];
    }

    public function testSendAuthCodeGrantRequest()
    {
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'code'          => $this->getAuthCode(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID),
                'redirect_uri'  => 'http://test.com',
            ]
        );

        self::assertArrayHasKey('token_type', $responseData);
        self::assertArrayHasKey('expires_in', $responseData);
        self::assertArrayHasKey('access_token', $responseData);
        self::assertArrayHasKey('refresh_token', $responseData);
    }

    public function testTryToSendAuthCodeGrantRequestWithPasswordGrantClient()
    {
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'code'          => $this->getAuthCode(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID),
                'redirect_uri'  => 'http://test.com',
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message'           => 'Client authentication failed',
            ],
            $responseData
        );
    }

    public function testTryToSendAuthCodeGrantRequestWithClientCredentialsGrantClient()
    {
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET,
                'code'          => $this->getAuthCode(LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID),
                'redirect_uri'  => 'http://test.com',
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message'           => 'Client authentication failed',
            ],
            $responseData
        );
    }

    public function testSendClientCredentialsGrantRequest()
    {
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET,
            ]
        );

        self::assertArrayHasKey('token_type', $responseData);
        self::assertArrayHasKey('expires_in', $responseData);
        self::assertArrayHasKey('access_token', $responseData);
    }

    public function testTryToSendClientCredentialsGrantRequestWithPasswordGrantClient()
    {
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message'           => 'Client authentication failed',
            ],
            $responseData
        );
    }

    public function testTryToSendClientCredentialsGrantRequestWithAuthCodeGrantClient()
    {
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message'           => 'Client authentication failed',
            ],
            $responseData
        );
    }

    public function testSendPasswordGrantRequest()
    {
        $userName = $this->getReference('user')->getUsername();
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username'      => $userName,
                'password'      => $userName,
            ]
        );

        self::assertArrayHasKey('token_type', $responseData);
        self::assertArrayHasKey('expires_in', $responseData);
        self::assertArrayHasKey('access_token', $responseData);
        self::assertArrayHasKey('refresh_token', $responseData);
    }

    public function testTryToSendPasswordGrantRequestWithClientCredentialsGrantClient()
    {
        $userName = $this->getReference('user')->getUsername();
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET,
                'username'      => $userName,
                'password'      => $userName,
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message'           => 'Client authentication failed',
            ],
            $responseData
        );
    }

    public function testTryToSendPasswordGrantRequestWithAuthCodeGrantClient()
    {
        $userName = $this->getReference('user')->getUsername();
        $responseData = $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadAuthorizationCodeGrantClient::OAUTH_CLIENT_SECRET,
                'username'      => $userName,
                'password'      => $userName,
            ],
            Response::HTTP_UNAUTHORIZED
        );

        self::assertEquals(
            [
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message'           => 'Client authentication failed',
            ],
            $responseData
        );
    }
}
