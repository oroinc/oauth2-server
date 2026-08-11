<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadClientCredentialsClient;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadUser;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 */
class SessionTransferOAuthServerTest extends OAuthServerTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadClientCredentialsClient::class, LoadUser::class]);
        $this->setSessionTransferAllowed(true);
    }

    public function testExchangeAndConsumeSessionTransferToken(): void
    {
        $responseData = $this->sendSessionTransferRequest(
            $this->createSourceAccessToken(),
            'oro_user_profile_view'
        );

        self::assertSame('SessionTransfer', $responseData['token_type']);
        self::assertSame(60, $responseData['expires_in']);
        self::assertStringStartsWith('stt_', $responseData['access_token']);
        self::assertArrayNotHasKey('session_transfer_uri', $responseData);
        $consumeUrl = $this->getUrl(
            'oro_oauth2_session_transfer_consume',
            ['token' => $responseData['access_token']]
        );

        $transferToken = $this->getSessionTransferToken($responseData['access_token']);
        self::assertNull($transferToken->getConsumedAt());
        self::assertSame('oro_user_profile_view', $transferToken->getRoute());
        self::assertSame([], $transferToken->getRouteParameters());
        self::assertSame([], $transferToken->getContextData());

        $this->client->request('GET', $consumeUrl);
        $response = $this->client->getResponse();

        self::assertResponseStatusCodeEquals($response, Response::HTTP_SEE_OTHER);
        self::assertSame($this->getUrl('oro_user_profile_view', [], true), $response->headers->get('Location'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', $response->headers->get('Cache-Control'));
        self::assertSame('no-cache', $response->headers->get('Pragma'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        $loggedUser = self::getContainer()->get('oro_security.token_accessor')->getUser();
        $expectedUser = $this->getReference(LoadUser::USER);
        self::assertInstanceOf(User::class, $loggedUser);
        self::assertSame($expectedUser->getId(), $loggedUser->getId());

        $transferToken = $this->getSessionTransferToken($responseData['access_token']);
        self::assertNotNull($transferToken->getConsumedAt());

        $this->client->request('GET', $consumeUrl);
        self::assertResponseStatusCodeEquals($this->client->getResponse(), Response::HTTP_GONE);
    }

    public function testExchangeIsRejectedWhenSessionTransferIsDisabled(): void
    {
        $sourceAccessToken = $this->createSourceAccessToken();
        $this->setSessionTransferAllowed(false);

        $responseData = $this->sendSessionTransferRequest(
            $sourceAccessToken,
            'oro_user_profile_view',
            Response::HTTP_UNAUTHORIZED
        );

        self::assertSame('invalid_client', $responseData['error']);
        self::assertNull($this->findSessionTransferToken());
    }

    public function testExchangeIsRejectedForStorefrontRoute(): void
    {
        $responseData = $this->sendSessionTransferRequest(
            $this->createSourceAccessToken(),
            'oro_customer_frontend_customer_user_profile',
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame('invalid_request', $responseData['error']);
        self::assertNull($this->findSessionTransferToken());
    }

    private function createSourceAccessToken(): string
    {
        $responseData = $this->sendTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
            'client_secret' => LoadClientCredentialsClient::OAUTH_CLIENT_SECRET
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
                'client_id' => LoadClientCredentialsClient::OAUTH_CLIENT_ID,
                'subject_token' => $subjectToken,
                'route' => $route,
                'route_parameters' => []
            ],
            $expectedStatusCode
        );
    }

    private function setSessionTransferAllowed(bool $allowed): void
    {
        $client = $this->getReference(LoadClientCredentialsClient::OAUTH_CLIENT_REFERENCE);
        $client->setSessionTransferAllowed($allowed);
        $this->getEntityManager()->flush();
    }

    private function getSessionTransferToken(string $plainToken): SessionTransferToken
    {
        $entityManager = $this->getSessionTransferTokenEntityManager();
        $entityManager->clear();

        return $entityManager->getRepository(SessionTransferToken::class)->findOneBy([
            'identifier' => hash('sha256', $plainToken)
        ]);
    }

    private function findSessionTransferToken(): ?SessionTransferToken
    {
        $entityManager = $this->getSessionTransferTokenEntityManager();
        $entityManager->clear();

        return $entityManager->getRepository(SessionTransferToken::class)->findOneBy([]);
    }

    private function getSessionTransferTokenEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManagerForClass(SessionTransferToken::class);
    }
}
