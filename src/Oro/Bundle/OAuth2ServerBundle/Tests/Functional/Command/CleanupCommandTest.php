<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Tests\Functional\DataFixtures\LoadCleanupCommandData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class CleanupCommandTest extends WebTestCase
{
    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadCleanupCommandData::class]);
    }

    public function testCleanup()
    {
        $result = self::runCommand('oro:cron:oauth-server:cleanup');
        self::assertContains('Completed', $result);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManagerForClass(Client::class);
        $em->clear();

        $clientRepo = $em->getRepository(Client::class);
        self::assertNotNull($clientRepo->findOneBy(['identifier' => 'client1']));
        self::assertNull($clientRepo->findOneBy(['identifier' => 'client2']));

        $accessTokenRepo = $em->getRepository(AccessToken::class);
        self::assertNotNull($accessTokenRepo->findOneBy(['identifier' => 'client1_token_not_expired']));
        self::assertNull($accessTokenRepo->findOneBy(['identifier' => 'client1_token_expired']));
        self::assertNull($accessTokenRepo->findOneBy(['identifier' => 'client2_token_not_expired']));
    }
}
