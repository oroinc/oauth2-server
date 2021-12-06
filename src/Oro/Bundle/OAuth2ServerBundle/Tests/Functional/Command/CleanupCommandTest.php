<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\AuthCode;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadCleanupCommandData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class CleanupCommandTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadCleanupCommandData::class]);
    }

    public function testCleanup()
    {
        $result = self::runCommand('oro:cron:oauth-server:cleanup');
        self::assertStringContainsString('Completed', $result);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManagerForClass(Client::class);
        $em->clear();

        $clientRepo = $em->getRepository(Client::class);
        self::assertNotNull($clientRepo->findOneBy(['identifier' => 'client1']));
        self::assertNull($clientRepo->findOneBy(['identifier' => 'client2']));

        $accessTokenRepo = $em->getRepository(AccessToken::class);
        self::assertNotNull($accessTokenRepo->findOneBy(['identifier' => 'client1_token_not_expired']));
        self::assertNull($accessTokenRepo->findOneBy(['identifier' => 'client1_token_expired']));
        self::assertNotNull(
            $accessTokenRepo->findOneBy(['identifier' => 'client1_token_expired_refresh_token_not_expired'])
        );
        self::assertNull($accessTokenRepo->findOneBy(['identifier' => 'client2_token_not_expired']));

        $refreshTokenRepo = $em->getRepository(RefreshToken::class);
        self::assertNull($refreshTokenRepo->findOneBy(['identifier' => 'expired_refresh_token']));
        self::assertNotNull($refreshTokenRepo->findOneBy(['identifier' => 'not_expired_refresh_token']));
        self::assertNull(
            $refreshTokenRepo->findOneBy(['identifier' => 'expired_refresh_token_for_expired_access_token'])
        );
        self::assertNotNull(
            $refreshTokenRepo->findOneBy(['identifier' => 'not_expired_refresh_token_for_expired_access_token'])
        );
        self::assertNull(
            $refreshTokenRepo->findOneBy(['identifier' => 'refresh_token_for_client_that_should_be_removed'])
        );

        $authCodeRepo = $em->getRepository(AuthCode::class);
        self::assertNull($authCodeRepo->findOneBy(['identifier' => 'auth_code_expired']));
        self::assertNotNull($authCodeRepo->findOneBy(['identifier' => 'auth_code_not_expired']));
    }
}
