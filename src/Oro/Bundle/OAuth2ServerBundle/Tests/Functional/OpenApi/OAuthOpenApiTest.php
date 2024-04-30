<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\OpenApi;

use Oro\Bundle\ApiBundle\Tests\Functional\OpenApi\OpenApiTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @group regression
 */
class OAuthOpenApiTest extends OpenApiTestCase
{
    public function testValidateGeneratedOpenApiForOAuthTokenEndpoint(): void
    {
        $result = self::runCommand(
            'oro:api:doc:open-api:dump',
            ['--view' => 'rest_json_api', '--format' => 'yaml', '--entity' => 'entityidentifiers'],
            false,
            true
        );
        $expected = Yaml::parse(file_get_contents(__DIR__ . '/data/rest_json_api_oauth.yml'));
        $actual = Yaml::parse($result);
        $this->assertOpenApiEquals($expected, $actual);
    }

    public function testValidateGeneratedOpenApiForOAuthTokenEndpointForFrontendApi(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('Could be tested only with Frontend bundle');
        }

        $result = self::runCommand(
            'oro:api:doc:open-api:dump',
            ['--view' => 'frontend_rest_json_api', '--format' => 'yaml', '--entity' => 'entityidentifiers'],
            false,
            true
        );
        $expected = Yaml::parse(file_get_contents(__DIR__ . '/data/frontend_rest_json_api_oauth.yml'));
        $actual = Yaml::parse($result);
        $this->assertOpenApiEquals($expected, $actual);
    }
}
