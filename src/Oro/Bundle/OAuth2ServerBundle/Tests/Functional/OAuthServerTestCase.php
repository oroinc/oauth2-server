<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiTestCase;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Component\HttpFoundation\Response;

class OAuthServerTestCase extends RestJsonApiTestCase
{
    /**
     * {@inheritdoc}
     * The method is overridden to not add HTTP_X-WSSE header to the request.
     */
    protected function checkWsseAuthHeader(array &$server): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function isStatelessRequest(array $server): bool
    {
        if (isset($server['HTTP_AUTHORIZATION']) && str_starts_with($server['HTTP_AUTHORIZATION'], 'Bearer ')) {
            return true;
        }

        return parent::isStatelessRequest($server);
    }

    protected function sendTokenRequest(array $requestData, int $expectedStatusCode = Response::HTTP_OK): array
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

    protected function sendRequest(string $method, string $uri, array $parameters = [], array $server = []): Response
    {
        $this->client->request($method, $uri, $parameters, [], $server);
        $this->assertSessionNotStarted($method, $uri, $server);

        return $this->client->getResponse();
    }

    /**
     * Asserts that lastUsedAt field have correct date.
     */
    public static function assertClientLastUsedValueIsCorrect(\DateTime $beginDatetime, Client $client): void
    {
        $beginTimestamp = $beginDatetime->getTimestamp();
        $lastUsedAtTimestamp = $client->getLastUsedAt()->getTimestamp();
        $currentDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $currentTimestamp = $currentDate->getTimestamp();

        self::assertGreaterThanOrEqual($beginTimestamp, $lastUsedAtTimestamp);
        self::assertLessThanOrEqual($currentTimestamp, $lastUsedAtTimestamp);
    }
}
