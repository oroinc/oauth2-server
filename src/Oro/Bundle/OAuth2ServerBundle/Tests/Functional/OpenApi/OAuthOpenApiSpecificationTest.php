<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Functional\OpenApi;

use Oro\Bundle\ApiBundle\Tests\Functional\OpenApi\OpenApiSpecificationTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @group regression
 */
class OAuthOpenApiSpecificationTest extends OpenApiSpecificationTestCase
{
    public function testValidateGeneratedOpenApiForOAuthTokenEndpointAndSecurity(): void
    {
        $result = self::runCommand(
            'oro:api:doc:open-api:dump',
            ['--view' => 'rest_json_api', '--format' => 'yaml', '--entity' => 'entityidentifiers'],
            false,
            true
        );
        $expected = Yaml::parse(file_get_contents(__DIR__ . '/data/rest_json_api_oauth.yml'));
        $backendPrefix = self::getContainer()->hasParameter('web_backend_prefix')
            ? self::getContainer()->getParameter('web_backend_prefix')
            : '';
        array_walk_recursive(
            $expected,
            function (&$val) use ($backendPrefix) {
                if (is_string($val)) {
                    $val = str_replace('%web_backend_prefix%', $backendPrefix, $val);
                }
            }
        );
        $actual = Yaml::parse($result);
        $this->assertOpenApiSpecificationEquals($expected, $actual);
    }

    public function testValidateGeneratedOpenApiForOAuthTokenEndpointAndSecurityForFrontendApi(): void
    {
        if (!class_exists('Oro\Bundle\FrontendBundle\OroFrontendBundle')) {
            self::markTestSkipped('can be tested only with FrontendBundle');
        }

        $result = self::runCommand(
            'oro:api:doc:open-api:dump',
            ['--view' => 'frontend_rest_json_api', '--format' => 'yaml', '--entity' => 'entityidentifiers'],
            false,
            true
        );
        $expected = Yaml::parse(file_get_contents(__DIR__ . '/data/frontend_rest_json_api_oauth.yml'));
        $actual = Yaml::parse($result);
        $this->assertOpenApiSpecificationEquals($expected, $actual);
    }
}
