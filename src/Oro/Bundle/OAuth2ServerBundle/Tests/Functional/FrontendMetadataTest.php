<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FrontendMetadataTest extends WebTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->initClient();
    }

    public function testTryToGetMetadataViaDirectUrl(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_server_frontend_metadata'),
            [],
            [],
            ['Accept' => 'application/json']
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
        self::assertResponseContentTypeContains($response, 'text/html');
        self::assertStringContainsString('Not Found', $response->getContent());
    }

    public function testGetMetadataViaWellKnownAlias(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_metadata_well_known_alias',
                ['metadataPath' => ltrim($this->getUrl('oro_oauth2_server_frontend_metadata'), '/')]
            ),
            [],
            [],
            ['Accept' => 'application/json']
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);
        self::assertResponseContentTypeContains($response, 'application/json');
        self::assertEquals(
            [
                'issuer' => $this->getUrl('oro_oauth2_server_frontend_metadata', [], true),
                'authorization_endpoint' => $this->getUrl('oro_oauth2_server_frontend_authenticate', [], true),
                'token_endpoint' => $this->getUrl('oro_oauth2_server_auth_token', [], true),
                'response_types_supported' => ['code'],
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
                'code_challenge_methods_supported' => ['S256'],
                'client_id_metadata_document_supported' => true
            ],
            self::jsonToArray($response->getContent())
        );
    }

    public function testTryToGetMetadataViaWellKnownAliasForUnknownAuthServer(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_metadata_well_known_alias',
                ['metadataPath' => 'unknown']
            ),
            [],
            [],
            ['Accept' => 'application/json']
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
        self::assertResponseContentTypeContains($response, 'text/plain');
        self::assertEquals('The OAuth server not found.', $response->getContent());
    }
}
