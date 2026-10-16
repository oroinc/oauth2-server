<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Provider;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\CurrentClientProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\SessionTransferAuthenticationToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\OAuthServerTestCase;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
final class CurrentClientProviderTest extends OAuthServerTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class, LoadUser::class]);
    }

    public function testGetClientReturnsClientOfBackOfficeUserSessionTransferOnLaterRequest(): void
    {
        $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE)->setSessionTransferAllowed(true);
        self::getContainer()->get('doctrine')->getManagerForClass(Client::class)->flush();
        $accessToken = $this->sendTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET,
        ])['access_token'];

        $sessionTransferToken = $this->sendTokenRequest([
            'grant_type' => Client::SESSION_TRANSFER,
            'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            'subject_token' => $accessToken,
            'route' => 'oro_user_profile_view',
            'route_parameters' => [],
        ])['access_token'];

        $this->client->request(
            'GET',
            $this->getUrl('oro_oauth2_session_transfer_consume', ['token' => $sessionTransferToken])
        );
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_SEE_OTHER);

        $this->client->request('GET', $this->getUrl('oro_user_profile_view'));
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_OK);

        $expectedClientId = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE)->getId();
        $token = self::getContainer()->get('security.token_storage')->getToken();
        self::assertInstanceOf(SessionTransferAuthenticationToken::class, $token);
        self::assertSame($expectedClientId, $token->getAttribute(CurrentClientProvider::CLIENT_ID_ATTRIBUTE));

        $client = self::getContainer()->get('oro_oauth2_server.tests.provider.current_client')->getClient();
        self::assertNotNull($client);
        self::assertSame($expectedClientId, $client->getId());
    }

    public function testGetClientReturnsClientOfApiRequestAccessToken(): void
    {
        $accessToken = $this->sendTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET,
        ])['access_token'];

        $response = $this->get(
            ['entity' => 'users', 'id' => '<toString(@user->id)>'],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken]
        );

        self::assertResponseStatusCodeEquals($response, Response::HTTP_OK);

        self::assertInstanceOf(OAuth2Token::class, self::getContainer()->get('security.token_storage')->getToken());

        $client = self::getContainer()->get('oro_oauth2_server.tests.provider.current_client')->getClient();
        self::assertNotNull($client);
        self::assertSame(
            $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE)->getId(),
            $client->getId()
        );
    }

    public function testGetClientReturnsNullForSessionNotOpenedBySessionTransfer(): void
    {
        $this->client->request('GET', $this->getUrl('oro_user_profile_view'), [], [], self::generateBasicAuthHeader());
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_OK);

        self::assertNull(self::getContainer()->get('oro_oauth2_server.tests.provider.current_client')->getClient());
    }
}
