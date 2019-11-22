<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class OAuthServerTestCase extends RestJsonApiTestCase
{
    /**
     * {@inheritdoc}
     * The method is overridden to not add HTTP_X-WSSE header to the request.
     */
    protected function checkWsseAuthHeader(array &$server)
    {
    }

    /**
     * @param array $requestData
     * @param int   $expectedStatusCode
     *
     * @return array
     */
    protected function sendTokenRequest(array $requestData, int $expectedStatusCode = Response::HTTP_OK)
    {
        $response = $this->sendRequest(
            'POST',
            $this->getUrl('oro_oauth2_server_auth_token'),
            $requestData
        );

        self::assertResponseStatusCodeEquals($response, $expectedStatusCode);
        if (Response::HTTP_OK === $expectedStatusCode) {
            self::assertResponseContentTypeEquals($response, 'application/json; charset=UTF-8');
        } elseif ($expectedStatusCode >= Response::HTTP_BAD_REQUEST) {
            self::assertResponseContentTypeEquals($response, 'application/json');
        }

        return self::jsonToArray($response->getContent());
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param array  $server
     *
     * @return Response
     */
    protected function sendRequest(string $method, string $uri, array $parameters = [], array $server = []): Response
    {
        $this->client->request($method, $uri, $parameters, [], $server);
        self::assertSessionNotStarted($method, $uri, $server);

        return $this->client->getResponse();
    }
}
