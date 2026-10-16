<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Provider;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\CurrentClientProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferCustomerVisitorAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendClientCredentialsClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendPasswordGrantClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\OAuthServerTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
final class FrontendCurrentClientProviderTest extends OAuthServerTestCase
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
            LoadFrontendPasswordGrantClient::class,
        ]);
        $this->getReference(LoadFrontendClientCredentialsClient::OAUTH_CLIENT_REFERENCE)
            ->setSessionTransferAllowed(true);
        $this->getReference(LoadFrontendPasswordGrantClient::OAUTH_CLIENT_REFERENCE)
            ->setSessionTransferAllowed(true);
        self::getContainer()->get('doctrine')->getManagerForClass(Client::class)->flush();
    }

    public function testGetClientReturnsClientOfCustomerUserSessionTransferOnLaterRequest(): void
    {
        $accessToken = $this->sendTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
            'client_secret' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_SECRET,
        ])['access_token'];

        $sessionTransferToken = $this->sendTokenRequest([
            'grant_type' => Client::SESSION_TRANSFER,
            'client_id' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
            'subject_token' => $accessToken,
            'route' => 'oro_customer_frontend_customer_user_profile',
            'route_parameters' => [],
        ])['access_token'];

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_frontend_session_transfer_consume', ['token' => $sessionTransferToken])
        );
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_SEE_OTHER);

        $this->client->request('GET', $this->getUrl('oro_frontend_root'));
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_OK);

        $expectedClientId = $this->getReference(LoadFrontendClientCredentialsClient::OAUTH_CLIENT_REFERENCE)->getId();
        $token = self::getContainer()->get('security.token_storage')->getToken();
        self::assertInstanceOf(SessionTransferAuthenticationToken::class, $token);
        self::assertSame($expectedClientId, $token->getAttribute(CurrentClientProvider::CLIENT_ID_ATTRIBUTE));

        $client = self::getContainer()->get('oro_oauth2_server.tests.provider.current_client')->getClient();
        self::assertNotNull($client);
        self::assertSame($expectedClientId, $client->getId());
    }

    public function testGetClientReturnsClientOfCustomerVisitorSessionTransferOnLaterRequest(): void
    {
        $accessToken = $this->sendTokenRequest([
            'grant_type' => 'password',
            'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
            'client_secret' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_SECRET,
            'username' => 'guest',
            'password' => 'guest',
        ])['access_token'];

        $sessionTransferToken = $this->sendTokenRequest([
            'grant_type' => Client::SESSION_TRANSFER,
            'client_id' => LoadFrontendPasswordGrantClient::OAUTH_CLIENT_ID,
            'subject_token' => $accessToken,
            'route' => 'oro_customer_frontend_customer_user_profile',
            'route_parameters' => [],
        ])['access_token'];

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_frontend_session_transfer_consume', ['token' => $sessionTransferToken])
        );
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_SEE_OTHER);

        $this->client->request('GET', $this->getUrl('oro_frontend_root'));
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_OK);

        $expectedClientId = $this->getReference(LoadFrontendPasswordGrantClient::OAUTH_CLIENT_REFERENCE)->getId();
        $token = self::getContainer()->get('security.token_storage')->getToken();
        self::assertInstanceOf(SessionTransferCustomerVisitorAuthenticationToken::class, $token);
        self::assertSame($expectedClientId, $token->getAttribute(CurrentClientProvider::CLIENT_ID_ATTRIBUTE));

        $client = self::getContainer()->get('oro_oauth2_server.tests.provider.current_client')->getClient();
        self::assertNotNull($client);
        self::assertSame($expectedClientId, $client->getId());
    }

    public function testGetClientReturnsNullForSessionNotOpenedBySessionTransfer(): void
    {
        $this->client->request('GET', $this->getUrl('oro_frontend_root'));
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_OK);

        self::assertNull(self::getContainer()->get('oro_oauth2_server.tests.provider.current_client')->getClient());
    }
}
