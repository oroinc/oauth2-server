<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Tests\Functional\ApiFeatureTrait;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class BackendApiFeatureOAuthServerTest extends OAuthServerTestCase
{
    use ApiFeatureTrait;

    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class]);
    }

    public function testGetAuthTokenOnEnabledFeature()
    {
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
            ],
            Response::HTTP_OK
        );

        self::assertEquals('Bearer', $accessToken['token_type']);
    }

    public function testGetAuthTokenOnDisabledFeature()
    {
        $this->disableApiFeature();
        try {
            $response = $this->sendTokenRequest(
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                    'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
                ],
                Response::HTTP_NOT_FOUND
            );
        } finally {
            $this->enableApiFeature();
        }

        self::assertEquals(
            [
                'error'             => 'not_available',
                'error_description' => 'Not found',
                'message'           => 'Not found'
            ],
            $response
        );
    }
}
