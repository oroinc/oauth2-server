<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendClientCredentialsClient;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class FrontendApiScopeOAuthServerTest extends WebTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->initClient();
        $this->loadFixtures([
            LoadFrontendClientCredentialsClient::class,
            LoadUser::class,
            'Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData'
        ]);
    }

    private function setOAuthClientApis(array $apis): void
    {
        /** @var Client $client */
        $client = $this->getReference(LoadFrontendClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        $client->setAllApis(false);
        $client->setApis($apis);
        self::getContainer()->get('doctrine')->getManagerForClass(Client::class)->flush();
    }

    private function getBearerAuthHeaderValue(): string
    {
        $this->client->request(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            [
                'grant_type' => 'client_credentials',
                'client_id' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_SECRET
            ]
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
        self::assertResponseContentTypeEquals($response, 'application/json; charset=UTF-8');
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseData = self::jsonToArray($response->getContent());

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    public function testRequestToAllowedFrontendApi(): void
    {
        $this->setOAuthClientApis(['frontend_rest_json_api']);

        $this->client->request(
            'GET',
            $this->getUrl('oro_frontend_rest_api_list', ['entity' => 'customerusers']),
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.api+json',
                'HTTP_AUTHORIZATION' => $this->getBearerAuthHeaderValue()
            ]
        );
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
    }

    public function testRequestToNotAllowedFrontendApi(): void
    {
        if (!class_exists('Oro\Bundle\ProductBundle\OroProductBundle')) {
            self::markTestSkipped('can be tested only with ProductBundle');
        }

        $this->setOAuthClientApis(['frontend_rest_json_api']);

        $this->client->request(
            'GET',
            $this->getUrl('oro_frontend_rest_api_list', ['entity' => 'customerusers']),
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.api+json',
                'HTTP_X-Product-ID' => 'sku',
                'HTTP_AUTHORIZATION' => $this->getBearerAuthHeaderValue()
            ]
        );
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FORBIDDEN);
    }
}
