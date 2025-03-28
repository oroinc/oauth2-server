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

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->initClient();
        $this->loadFixtures([
            LoadFrontendPasswordGrantClient::class,
            LoadCustomerUserData::class
        ]);
    }

    public function testGetAuthTokenOnEnabledFeature(): void
    {
        $accessToken = $this->sendTokenRequest(
            [
                'grant_type' => 'password',
                'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                'username' => 'grzegorz.brzeczyszczykiewicz@example.com',
                'password' => 'test'
            ]
        );

        self::assertEquals('Bearer', $accessToken['token_type']);
    }

    public function testGetAuthTokenOnEnabledFeatureAndDisabledGuestAccess(): void
    {
        $configManager = self::getConfigManager();
        $configManager->set('oro_frontend.guest_access_enabled', false);
        $configManager->flush();
        try {
            $accessToken = $this->sendTokenRequest(
                [
                    'grant_type' => 'password',
                    'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                    'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                    'username' => 'grzegorz.brzeczyszczykiewicz@example.com',
                    'password' => 'test'
                ]
            );
        } finally {
            $configManager->set('oro_frontend.guest_access_enabled', true);
            $configManager->flush();
        }

        self::assertEquals('Bearer', $accessToken['token_type']);
    }

    public function testGetAuthTokenOnDisabledFeature(): void
    {
        $this->disableApiFeature(self::API_FEATURE_NAME);
        try {
            $response = $this->sendTokenRequest(
                [
                    'grant_type' => 'password',
                    'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                    'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                    'username' => 'grzegorz.brzeczyszczykiewicz@example.com',
                    'password' => 'test'
                ],
                Response::HTTP_UNAUTHORIZED
            );
        } finally {
            $this->enableApiFeature(self::API_FEATURE_NAME);
        }

        self::assertEquals(
            [
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message' => 'Client authentication failed'
            ],
            $response
        );
    }

    public function testTryToGetAuthTokenOnDisabledFeatureAndDisabledGuestAccess(): void
    {
        $configManager = self::getConfigManager();
        $configManager->set('oro_frontend.guest_access_enabled', false);
        $configManager->flush();
        $this->disableApiFeature(self::API_FEATURE_NAME);
        try {
            $response = $this->sendTokenRequest(
                [
                    'grant_type' => 'password',
                    'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
                    'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
                    'username' => 'grzegorz.brzeczyszczykiewicz@example.com',
                    'password' => 'test'
                ],
                Response::HTTP_UNAUTHORIZED
            );
        } finally {
            $this->enableApiFeature(self::API_FEATURE_NAME);
            $configManager->set('oro_frontend.guest_access_enabled', true);
            $configManager->flush();
        }

        self::assertEquals(
            [
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
                'message' => 'Client authentication failed'
            ],
            $response
        );
    }
}
