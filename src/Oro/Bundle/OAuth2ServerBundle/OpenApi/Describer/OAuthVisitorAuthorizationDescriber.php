<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Describer\DescriberInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ErrorResponseStorageAwareInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ErrorResponseStorageAwareTrait;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ResponseStorageAwareInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ResponseStorageAwareTrait;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Util;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Describes the OAuth 2.0 authorization endpoint for storefront visitors (guests) in OpenAPI specification.
 */
class OAuthVisitorAuthorizationDescriber implements
    DescriberInterface,
    ResponseStorageAwareInterface,
    ErrorResponseStorageAwareInterface
{
    use ResponseStorageAwareTrait;
    use ErrorResponseStorageAwareTrait;

    private const string MEDIA_TYPE = 'application/json';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[\Override]
    public function describe(OA\OpenApi $api, array $options): void
    {
        if (Generator::isDefault($api->paths)) {
            $api->paths = [];
        }

        $url = $this->urlGenerator->generate('oro_oauth2_server_frontend_authenticate_visitor');
        $path = Util::createChildItem(OA\PathItem::class, $api, $url);
        $path->path = $url;
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $api->paths[] = $path;

        $operation = Util::createChildItem(OA\Get::class, $path, 'get');
        $path->get = $operation;
        $operation->operationId = 'oauthtoken-authorize-visitor-get';
        $operation->summary = 'Get OAuth 2.0 authorization code for a visitor';
        $operation->description = 'Obtain the OAuth 2.0 authorization code for a storefront visitor (guest).';
        $operation->tags = ['oauth'];
        $operation->parameters = [
            $this->createQueryParameter($operation, 'response_type', 'The response type.', true, ['code']),
            $this->createQueryParameter($operation, 'client_id', 'The client ID.', true),
            $this->createQueryParameter($operation, 'redirect_uri', 'The client redirect URI.'),
            $this->createQueryParameter($operation, 'code_challenge', 'The code challenge for the PKCE request.'),
            $this->createQueryParameter(
                $operation,
                'code_challenge_method',
                'The code challenge method for the PKCE request.',
                false,
                ['plain', 'S256']
            ),
        ];
        $operation->responses = [];
        $this->registerResponse($api, $operation, 302, 'Returned when successful');
        $this->registerErrorResponse($api, $operation, 400, 'Returned when the request data is not valid');
        $this->registerErrorResponse($api, $operation, 401, 'Returned when the server denied the request');
        $this->registerErrorResponse($api, $operation, 500, 'Returned when an unexpected error occurs');
    }

    /**
     * @param string[]|null $values The allowed values of the parameter; null when any string is accepted
     */
    private function createQueryParameter(
        OA\Operation $operation,
        string $name,
        string $description,
        bool $required = false,
        ?array $values = null
    ): OA\Parameter {
        $parameter = Util::createChildItem(OA\QueryParameter::class, $operation);
        $parameter->name = $name;
        if ($required) {
            $parameter->required = true;
        }
        $parameter->description = $description;
        $schema = Util::createType($parameter, Util::TYPE_STRING);
        if ($values) {
            $schema->enum = $values;
        }
        $parameter->schema = $schema;

        return $parameter;
    }

    private function registerResponse(
        OA\OpenApi $api,
        OA\Operation $operation,
        int $statusCode,
        string $description
    ): void {
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $operation->responses[] = Util::createResponseRef(
            $operation,
            $statusCode,
            $this->responseStorage->registerResponse($api, $description, self::MEDIA_TYPE, null)->response
        );
    }

    private function registerErrorResponse(
        OA\OpenApi $api,
        OA\Operation $operation,
        int $statusCode,
        string $description
    ): void {
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $operation->responses[] = Util::createResponseRef(
            $operation,
            $statusCode,
            $this->errorResponseStorage
                ->registerErrorResponse(
                    $api,
                    $statusCode,
                    $description,
                    self::MEDIA_TYPE,
                    OAuthDescriber::OAUTH_FAILURE
                )
                ->response
        );
    }
}
