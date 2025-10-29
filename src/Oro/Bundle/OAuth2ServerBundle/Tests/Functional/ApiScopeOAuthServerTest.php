<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class ApiScopeOAuthServerTest extends WebTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class]);
    }

    private function setOAuthClientApis(array $apis): void
    {
        /** @var Client $client */
        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
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
                'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
            ]
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
        self::assertResponseContentTypeEquals($response, 'application/json; charset=UTF-8');
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $responseData = self::jsonToArray($response->getContent());

        return sprintf('Bearer %s', $responseData['access_token']);
    }

    public function testRequestToAllowedApi(): void
    {
        $this->setOAuthClientApis(['rest_json_api']);

        $this->client->request(
            'GET',
            $this->getUrl('oro_rest_api_list', ['entity' => 'users']),
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

    public function testRequestToNotAllowedApi(): void
    {
        $this->setOAuthClientApis(['rest_json_api']);

        $this->client->request(
            'GET',
            $this->getUrl('oro_rest_api_list', ['entity' => 'users']),
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => $this->getBearerAuthHeaderValue()
            ]
        );
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FORBIDDEN);
    }

    public function testRequestToAllowedOldApi(): void
    {
        $this->setOAuthClientApis(['default']);

        $this->client->request(
            'GET',
            $this->getUrl('oro_api_get_users'),
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => $this->getBearerAuthHeaderValue()
            ]
        );
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
    }

    public function testRequestToNotAllowedOldApi(): void
    {
        $this->setOAuthClientApis(['rest_json_api']);

        $this->client->request(
            'GET',
            $this->getUrl('oro_api_get_users'),
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => $this->getBearerAuthHeaderValue()
            ]
        );
        $response = $this->client->getResponse();
        self::assertResponseStatusCodeEquals($response, Response::HTTP_FORBIDDEN);
    }
}
