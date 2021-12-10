<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadSecondOrgData;

class OAuthApiRequestsAtMultiOrgTest extends OAuthServerTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\OrganizationProBundle\OroOrganizationProBundle')) {
            self::markTestSkipped('The tests can be executed only with enterprise OrganizationProBundle bundle.');
        }

        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class, LoadSecondOrgData::class]);
    }

    private function getBearerAuthHeaderValue(string $clientId, string $clientSecret): string
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret
            ]
        );

        $response = self::jsonToArray($response->getContent());
        return sprintf('Bearer %s', $response['access_token']);
    }

    public function testGetListOnFirstOrganizationApplicationRequest(): void
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue(
            LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
        );

        $response = $this->cget(['entity' => 'users'], [], ['HTTP_AUTHORIZATION' => $authorizationHeader]);
        $this->assertResponseContains(
            ['data' => [['type' => 'users', 'id'   => '<toString(@user->id)>']]],
            $response
        );
    }

    public function testGetListOnSecondOrganizationApplicationRequest(): void
    {
        $authorizationHeader = $this->getBearerAuthHeaderValue(
            LoadSecondOrgData::OAUTH_CLIENT_ID,
            LoadSecondOrgData::OAUTH_CLIENT_SECRET
        );

        $response = $this->cget(['entity' => 'users'], [], ['HTTP_AUTHORIZATION' => $authorizationHeader]);
        $this->assertResponseContains(
            ['data' => [['type' => 'users', 'id'   => '<toString(@second_user->id)>']]],
            $response
        );
    }
}
