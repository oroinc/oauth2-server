<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Tests\Functional\ApiFeatureTrait;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendPasswordGrantClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class FrontendApiFeatureOAuthServerTest extends OAuthServerTestCase
{
    use ApiFeatureTrait;

    private const API_FEATURE_NAME = 'oro_frontend.web_api';

    protected function setUp()
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('Could be tested only with Customer bundle');
        }

        $this->initClient();
        $this->loadFixtures([
            LoadFrontendPasswordGrantClient::class,
            LoadCustomerUserData::class
        ]);
    }

    public function testGetAuthTokenOnEnabledFeature()
    {
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type'    => 'password',
                'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username'      => 'grzegorz.brzeczyszczykiewicz@example.com',
                'password'      => 'test'
            ],
            Response::HTTP_OK
        );

        self::assertEquals('Bearer', $accessToken['token_type']);
    }

    public function testGetAuthTokenOnDisabledFeature()
    {
        $this->disableApiFeature(self::API_FEATURE_NAME);
        try {
            $response = $this->sendTokenRequest(
                [
                    'grant_type'    => 'password',
                    'client_id'     => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                    'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                    'username'      => 'grzegorz.brzeczyszczykiewicz@example.com',
                    'password'      => 'test'
                ],
                Response::HTTP_NOT_FOUND
            );
        } finally {
            $this->enableApiFeature(self::API_FEATURE_NAME);
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
