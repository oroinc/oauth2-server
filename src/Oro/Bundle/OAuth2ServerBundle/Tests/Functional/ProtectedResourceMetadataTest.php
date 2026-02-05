<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProtectedResourceMetadataTest extends WebTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
    }

    public function testGetResourceMetadata(): void
    {
        $backendPrefix = self::getContainer()->hasParameter('web_backend_prefix')
            ? self::getContainer()->getParameter('web_backend_prefix')
            : '';

        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_protected_resource',
                ['resourcePath' => ltrim($backendPrefix . '/user/create', '/')]
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
                'resource_name' => 'Test Resource',
                'resource' => $this->getUrl('oro_user_create', [], true),
                'authorization_servers' => [$this->getUrl('oro_oauth2_server_metadata', [], true)],
                'bearer_methods_supported' => ['header'],
                'scopes_supported' => ['scope:test'],
                'x-test-option' => 'test resource'
            ],
            self::jsonToArray($response->getContent())
        );
    }

    public function testGetResourceMetadataForResourceWithRouteParameters(): void
    {
        $backendPrefix = self::getContainer()->hasParameter('web_backend_prefix')
            ? self::getContainer()->getParameter('web_backend_prefix')
            : '';

        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_protected_resource',
                ['resourcePath' => ltrim($backendPrefix . '/user/update/1', '/')]
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
                'resource_name' => 'Test Resource With Route Parameters',
                'resource' => $this->getUrl('oro_user_update', ['id' => 1], true),
                'authorization_servers' => [$this->getUrl('oro_oauth2_server_metadata', [], true)],
                'bearer_methods_supported' => ['header'],
                'scopes_supported' => ['scope:test'],
                'x-test-option' => 'test resource with route_params'
            ],
            self::jsonToArray($response->getContent())
        );
    }

    public function testGetResourceMetadataForFrontendResource(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_protected_resource',
                ['resourcePath' => 'customer/user/create']
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
                'resource_name' => 'Test Frontend Resource',
                'resource' => $this->getUrl('oro_customer_frontend_customer_user_create', [], true),
                'authorization_servers' => [$this->getUrl('oro_oauth2_server_frontend_metadata', [], true)],
                'bearer_methods_supported' => ['header'],
                'scopes_supported' => ['scope:test'],
                'x-test-option' => 'test frontend resource'
            ],
            self::jsonToArray($response->getContent())
        );
    }

    public function testTryToGetResourceMetadataForUnknownResource(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_protected_resource',
                ['resourcePath' => 'test/unknown']
            ),
            [],
            [],
            ['Accept' => 'application/json']
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
        self::assertResponseContentTypeContains($response, 'text/plain');
        self::assertEquals('The resource not found.', $response->getContent());
    }

    public function testTryToGetResourceMetadataWhenConfiguredResourcePathDoesNotMatchResolvedByRoutePath(): void
    {
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_oauth2_server_protected_resource',
                ['resourcePath' => 'test/invalid-path']
            ),
            [],
            [],
            ['Accept' => 'application/json']
        );
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_NOT_FOUND);
        self::assertResponseContentTypeContains($response, 'text/plain');
        self::assertEquals('The resource not found.', $response->getContent());
    }
}
