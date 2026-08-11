<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomerUserData;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadFrontendClientCredentialsClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class FrontendSessionTransferOAuthServerTest extends OAuthServerTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->initClient();
        $this->loadFixtures([LoadFrontendClientCredentialsClient::class]);

        $client = $this->getReference(LoadFrontendClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        $client->setSessionTransferAllowed(true);
        $this->getEntityManager()->flush();
    }

    public function testExchangeAndConsumeStorefrontSessionTransferToken(): void
    {
        $responseData = $this->sendSessionTransferRequest(
            $this->createSourceAccessToken(),
            'oro_customer_frontend_customer_user_profile'
        );

        self::assertEquals('SessionTransfer', $responseData['token_type']);
        self::assertEquals(60, $responseData['expires_in']);
        self::assertStringStartsWith('stt_', $responseData['access_token']);
        self::assertArrayNotHasKey('session_transfer_uri', $responseData);
        $consumeUrl = $this->getUrl(
            'oro_oauth2_frontend_session_transfer_consume',
            ['token' => $responseData['access_token']]
        );

        $transferToken = $this->getSessionTransferToken($responseData['access_token']);
        self::assertNull($transferToken->getConsumedAt());
        self::assertEquals('oro_customer_frontend_customer_user_profile', $transferToken->getRoute());
        self::assertArrayHasKey('website_id', $transferToken->getContextData());

        $this->client->request('GET', $consumeUrl);
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_SEE_OTHER);
        self::assertEquals(
            $this->getUrl('oro_customer_frontend_customer_user_profile', [], true),
            $response->headers->get('Location')
        );

        $loggedUser = self::getContainer()->get('oro_security.token_accessor')->getUser();
        $expectedUser = $this->getReference(LoadCustomerUserData::EMAIL);
        self::assertInstanceOf(CustomerUser::class, $loggedUser);
        self::assertEquals($expectedUser->getId(), $loggedUser->getId());

        $transferToken = $this->getSessionTransferToken($responseData['access_token']);
        self::assertNotNull($transferToken->getConsumedAt());
    }

    public function testExchangeIsRejectedForBackOfficeRoute(): void
    {
        $responseData = $this->sendSessionTransferRequest(
            $this->createSourceAccessToken(),
            'oro_user_profile_view',
            Response::HTTP_BAD_REQUEST
        );

        self::assertEquals('invalid_request', $responseData['error']);
    }

    private function createSourceAccessToken(): string
    {
        $responseData = $this->sendTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
            'client_secret' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_SECRET
        ]);

        return $responseData['access_token'];
    }

    private function sendSessionTransferRequest(
        string $subjectToken,
        string $route,
        int $expectedStatusCode = Response::HTTP_OK
    ): array {
        return $this->sendTokenRequest(
            [
                'grant_type' => Client::SESSION_TRANSFER,
                'client_id' => LoadFrontendClientCredentialsClient::OAUTH_CLIENT_ID,
                'subject_token' => $subjectToken,
                'route' => $route,
                'route_parameters' => []
            ],
            $expectedStatusCode
        );
    }

    private function getSessionTransferToken(string $plainToken): SessionTransferToken
    {
        $entityManager = $this->getSessionTransferTokenEntityManager();
        $entityManager->clear();

        return $entityManager->getRepository(SessionTransferToken::class)->findOneBy([
            'identifier' => hash('sha256', $plainToken)
        ]);
    }

    private function getSessionTransferTokenEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManagerForClass(SessionTransferToken::class);
    }
}
