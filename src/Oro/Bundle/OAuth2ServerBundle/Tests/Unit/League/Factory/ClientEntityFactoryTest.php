<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Factory;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Factory\ClientEntityFactory;
use PHPUnit\Framework\TestCase;

class ClientEntityFactoryTest extends TestCase
{
    /**
     * @dataProvider createClientEntityDataProvider
     */
    public function testCreateClientEntity(
        string $identifier,
        string $name,
        array $redirectUris,
        bool $confidential,
        bool $frontend,
        bool $plainTextPkceAllowed,
        bool $skipAuthorizeClientAllowed,
        ?array $expectedRedirectUris = null
    ): void {
        $client = new Client();
        $client->setIdentifier($identifier);
        $client->setName($name);
        $client->setRedirectUris($redirectUris);
        $client->setConfidential($confidential);
        $client->setFrontend($frontend);
        $client->setPlainTextPkceAllowed($plainTextPkceAllowed);
        $client->setSkipAuthorizeClientAllowed($skipAuthorizeClientAllowed);

        $factory = new ClientEntityFactory();
        $clientEntity = $factory->createClientEntity($client);

        self::assertSame($identifier, $clientEntity->getIdentifier());
        self::assertSame($name, $clientEntity->getName());
        self::assertSame($expectedRedirectUris ?? $redirectUris, $clientEntity->getRedirectUri());
        self::assertSame($confidential, $clientEntity->isConfidential());
        self::assertSame($frontend, $clientEntity->isFrontend());
        self::assertSame($plainTextPkceAllowed, $clientEntity->isPlainTextPkceAllowed());
        self::assertSame($skipAuthorizeClientAllowed, $clientEntity->isSkipAuthorizeClientAllowed());
    }

    public static function createClientEntityDataProvider(): array
    {
        return [
            [
                'identifier' => 'test_id',
                'name' => 'Test',
                'redirectUris' => ['https://test.com/callback'],
                'confidential' => false,
                'frontend' => false,
                'plainTextPkceAllowed' => false,
                'skipAuthorizeClientAllowed' => false
            ],
            [
                'identifier' => 'test_id',
                'name' => 'Test',
                'redirectUris' => ['https://test.com/callback2', '', 'https://test.com/callback2', null],
                'confidential' => false,
                'frontend' => false,
                'plainTextPkceAllowed' => false,
                'skipAuthorizeClientAllowed' => false,
                'expectedRedirectUris' => ['https://test.com/callback2', 'https://test.com/callback2']
            ],
            [
                'identifier' => 'test_id',
                'name' => 'Test',
                'redirectUris' => ['https://test.com/callback'],
                'confidential' => true,
                'frontend' => false,
                'plainTextPkceAllowed' => false,
                'skipAuthorizeClientAllowed' => false
            ],
            [
                'identifier' => 'test_id',
                'name' => 'Test',
                'redirectUris' => ['https://test.com/callback'],
                'confidential' => false,
                'frontend' => true,
                'plainTextPkceAllowed' => false,
                'skipAuthorizeClientAllowed' => false
            ],
            [
                'identifier' => 'test_id',
                'name' => 'Test',
                'redirectUris' => ['https://test.com/callback'],
                'confidential' => false,
                'frontend' => false,
                'plainTextPkceAllowed' => true,
                'skipAuthorizeClientAllowed' => false
            ],
            [
                'identifier' => 'test_id',
                'name' => 'Test',
                'redirectUris' => ['https://test.com/callback'],
                'confidential' => false,
                'frontend' => false,
                'plainTextPkceAllowed' => false,
                'skipAuthorizeClientAllowed' => true
            ],
        ];
    }
}
