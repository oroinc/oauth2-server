<?php

namespace Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Describer\DescriberInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Util;
use Oro\Bundle\ApiBundle\ApiDoc\RestDocViewDetector;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Describes OAuth and Bearer security schemes in OpenAPI specification.
 */
class OAuthSecurityDescriber implements DescriberInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private RestDocViewDetector $docViewDetector;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        RestDocViewDetector $docViewDetector
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->docViewDetector = $docViewDetector;
    }

    /**
     * {@inheritDoc}
     */
    public function describe(OA\OpenApi $api, array $options): void
    {
        Util::ensureComponentCollectionInitialized($api, 'securitySchemes');
        $oAuth2SecurityScheme = $this->createOAuth2SecurityScheme($api);
        $bearerSecurityScheme = $this->createBearerSecurityScheme($api);
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $api->components->securitySchemes[] = $oAuth2SecurityScheme;
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $api->components->securitySchemes[] = $bearerSecurityScheme;

        if (Generator::isDefault($api->security)) {
            $api->security = [];
        }
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $api->security[] = [$oAuth2SecurityScheme->securityScheme => []];
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $api->security[] = [$bearerSecurityScheme->securityScheme => []];
    }

    private function createOAuth2SecurityScheme(OA\OpenApi $api): OA\SecurityScheme
    {
        $securityScheme = Util::createChildItem(OA\SecurityScheme::class, $api->components);
        $securityScheme->securityScheme = 'oAuth2';
        $securityScheme->type = 'oauth2';
        $securityScheme->description =
            'For more information, see https://doc.oroinc.com/master/api/authentication/oauth/';
        $securityScheme->flows = [
            $this->createClientCredentialsFlow($securityScheme),
            $this->createAuthorizationCodeFlow($securityScheme),
            $this->createPasswordFlow($securityScheme)
        ];

        return $securityScheme;
    }

    private function createBearerSecurityScheme(OA\OpenApi $api): OA\SecurityScheme
    {
        $securityScheme = Util::createChildItem(OA\SecurityScheme::class, $api->components);
        $securityScheme->securityScheme = 'bearerAuth';
        $securityScheme->type = 'http';
        $securityScheme->scheme = 'bearer';

        return $securityScheme;
    }

    private function createClientCredentialsFlow(OA\SecurityScheme $securityScheme): OA\Flow
    {
        $flow = Util::createChildItem(OA\Flow::class, $securityScheme);
        $flow->flow = 'clientCredentials';
        $flow->tokenUrl = $this->urlGenerator->generate('oro_oauth2_server_auth_token');
        $flow->scopes = [];

        return $flow;
    }

    private function createAuthorizationCodeFlow(OA\SecurityScheme $securityScheme): OA\Flow
    {
        $flow = Util::createChildItem(OA\Flow::class, $securityScheme);
        $flow->flow = 'authorizationCode';
        $flow->authorizationUrl = $this->docViewDetector->getRequestType()->contains('frontend')
            ? $this->urlGenerator->generate('oro_oauth2_server_frontend_authenticate')
            : $this->urlGenerator->generate('oro_oauth2_server_authenticate');
        $flow->tokenUrl = $this->urlGenerator->generate('oro_oauth2_server_auth_token');
        $flow->scopes = [];

        return $flow;
    }

    private function createPasswordFlow(OA\SecurityScheme $securityScheme): OA\Flow
    {
        $flow = Util::createChildItem(OA\Flow::class, $securityScheme);
        $flow->flow = 'password';
        $flow->tokenUrl = $this->urlGenerator->generate('oro_oauth2_server_auth_token');
        $flow->scopes = [];

        return $flow;
    }
}
