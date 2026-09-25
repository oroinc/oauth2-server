<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\DependencyInjectionUtil;
use Oro\Bundle\OAuth2ServerBundle\DependencyInjection\Compiler\OpenApiCompilerPass;
use Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer\OAuthDescriber;
use Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer\OAuthSecurityDescriber;
use Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer\OAuthVisitorAuthorizationDescriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class OpenApiCompilerPassTest extends TestCase
{
    private const string BACKEND_VIEW = 'rest_json_api';
    private const string FRONTEND_VIEW = 'frontend_rest_json_api';
    private const string PLAIN_REST_VIEW = 'rest_plain';
    private const string FRONTEND_REQUEST_TYPE = 'frontend';
    private const string DESCRIBER_TAG_PREFIX = 'oro.api.open_api.describer.';
    private const string SECURITY_DESCRIBER_ID_PREFIX = 'oro_oauth2_server.open_api.security_describer.';
    private const string DESCRIBER_ID_PREFIX = 'oro_oauth2_server.open_api.describer.';
    private const string VISITOR_DESCRIBER_ID_PREFIX = 'oro_oauth2_server.open_api.visitor_authorization_describer.';

    private ContainerBuilder $container;

    private OpenApiCompilerPass $compiler;

    #[\Override]
    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compiler = new OpenApiCompilerPass();
    }

    public function testProcessForBackendJsonApiView(): void
    {
        DependencyInjectionUtil::setConfig($this->container, [
            'api_doc_views' => [
                self::BACKEND_VIEW => ['request_type' => [RequestType::REST, RequestType::JSON_API]],
            ],
        ]);

        $this->compiler->process($this->container);

        $securityDescriber = $this->container->getDefinition(self::SECURITY_DESCRIBER_ID_PREFIX . self::BACKEND_VIEW);
        self::assertSame(OAuthSecurityDescriber::class, $securityDescriber->getClass());
        self::assertEquals(
            [new Reference('router'), new Reference('oro_api.rest.doc_view_detector')],
            $securityDescriber->getArguments()
        );
        self::assertSame([[]], $securityDescriber->getTag(self::DESCRIBER_TAG_PREFIX . self::BACKEND_VIEW));

        $describer = $this->container->getDefinition(self::DESCRIBER_ID_PREFIX . self::BACKEND_VIEW);
        self::assertSame(OAuthDescriber::class, $describer->getClass());
        self::assertEquals(
            [new Reference('router'), new Reference('oro_api.api_doc.open_api.data_type_describe_helper')],
            $describer->getArguments()
        );
        self::assertSame([[]], $describer->getTag(self::DESCRIBER_TAG_PREFIX . self::BACKEND_VIEW));

        self::assertFalse($this->container->hasDefinition(self::VISITOR_DESCRIBER_ID_PREFIX . self::BACKEND_VIEW));
    }

    public function testProcessForFrontendJsonApiView(): void
    {
        DependencyInjectionUtil::setConfig($this->container, [
            'api_doc_views' => [
                self::FRONTEND_VIEW => [
                    'request_type' => [RequestType::REST, RequestType::JSON_API, self::FRONTEND_REQUEST_TYPE],
                ],
            ],
        ]);

        $this->compiler->process($this->container);

        $describer = $this->container->getDefinition(self::DESCRIBER_ID_PREFIX . self::FRONTEND_VIEW);
        self::assertSame(OAuthDescriber::class, $describer->getClass());
        self::assertEquals(
            [new Reference('router'), new Reference('oro_api.api_doc.open_api.data_type_describe_helper')],
            $describer->getArguments()
        );
        self::assertSame([[]], $describer->getTag(self::DESCRIBER_TAG_PREFIX . self::FRONTEND_VIEW));

        $visitorDescriber = $this->container->getDefinition(self::VISITOR_DESCRIBER_ID_PREFIX . self::FRONTEND_VIEW);
        self::assertSame(OAuthVisitorAuthorizationDescriber::class, $visitorDescriber->getClass());
        self::assertEquals([new Reference('router')], $visitorDescriber->getArguments());
        self::assertSame([[]], $visitorDescriber->getTag(self::DESCRIBER_TAG_PREFIX . self::FRONTEND_VIEW));

        // the describers of a view share the tag priority, so the registration order defines the order
        // of the paths in the generated specification: the token endpoint first, the visitor endpoint next
        self::assertSame(
            [
                'service_container',
                self::SECURITY_DESCRIBER_ID_PREFIX . self::FRONTEND_VIEW,
                self::DESCRIBER_ID_PREFIX . self::FRONTEND_VIEW,
                self::VISITOR_DESCRIBER_ID_PREFIX . self::FRONTEND_VIEW,
            ],
            array_keys($this->container->getDefinitions())
        );
    }

    public function testProcessSkipsViewsWithoutJsonApiRequestType(): void
    {
        DependencyInjectionUtil::setConfig($this->container, [
            'api_doc_views' => [
                self::PLAIN_REST_VIEW => ['request_type' => [RequestType::REST]],
                'view_without_request_type' => ['request_type' => []],
            ],
        ]);

        $this->compiler->process($this->container);

        self::assertSame(['service_container'], array_keys($this->container->getDefinitions()));
    }
}
