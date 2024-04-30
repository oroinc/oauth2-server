<?php

namespace Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Describer\DataTypeDescribeHelper;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Describer\DescriberInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ErrorResponseStorageAwareInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ErrorResponseStorageAwareTrait;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ModelNameUtil;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\RequestBodyStorageAwareInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\RequestBodyStorageAwareTrait;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ResponseStorageAwareInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ResponseStorageAwareTrait;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\SchemaStorageAwareInterface;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\SchemaStorageAwareTrait;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Util;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Describes OAuth authorization endpoint in OpenAPI specification.
 */
class OAuthDescriber implements
    DescriberInterface,
    SchemaStorageAwareInterface,
    RequestBodyStorageAwareInterface,
    ResponseStorageAwareInterface,
    ErrorResponseStorageAwareInterface
{
    use SchemaStorageAwareTrait;
    use RequestBodyStorageAwareTrait;
    use ResponseStorageAwareTrait;
    use ErrorResponseStorageAwareTrait;

    private const OAUTH_FAILURE = 'oauthTokenFailure';

    private UrlGeneratorInterface $urlGenerator;
    private DataTypeDescribeHelper $dataTypeDescribeHelper;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        DataTypeDescribeHelper $dataTypeDescribeHelper
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->dataTypeDescribeHelper = $dataTypeDescribeHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function describe(OA\OpenApi $api, array $options): void
    {
        if (Generator::isDefault($api->paths)) {
            $api->paths = [];
        }

        $url = $this->urlGenerator->generate('oro_oauth2_server_auth_token');
        $path = Util::createChildItem(OA\PathItem::class, $api, $url);
        $path->path = $url;
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $api->paths[] = $path;
        $this->registerTokenPath($api, $path);
        $this->registerTokenOptionsPath($api, $path);
        $this->registerFailureObject($api);
    }

    private function registerTokenPath(OA\OpenApi $api, OA\PathItem $path): void
    {
        $operation = Util::createChildItem(OA\Post::class, $path, 'post');
        $path->post = $operation;
        $operation->operationId = 'oauthtoken-post';
        $operation->summary = 'Get OAuth 2.0 access token';
        $operation->description = 'Retrieve JSON object that contains OAuth 2.0 access token.';
        $operation->tags = ['oauth'];
        $this->registerRequestBody($api, $operation, [
            'grant_type'    => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The OAuth grant type.',
                'required'    => true,
                'values'      => ['authorization_code', 'client_credentials', 'password', 'refresh_token']
            ],
            'client_id'     => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The client ID.',
                'required'    => true
            ],
            'client_secret' => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The client secret.'
            ],
            'redirect_uri'  => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The client redirect URI for the "authorization_code" grant type.'
            ],
            'code'          => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The authorization code for the "authorization_code" grant type.'
            ],
            'code_verifier' => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The code verifier for the PKCE request.'
            ],
            'username'      => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The user username for the "password" grant type.'
            ],
            'password'      => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The user password for the "password" grant type.'
            ],
            'refresh_token' => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The refresh token for the "refresh_token" grant type.'
            ],
        ]);
        $operation->responses = [];
        $this->registerResponse($api, $operation, 200, 'Returned when successful', [
            'token_type'   => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The type of the access token.',
                'required'    => true,
                'values'      => ['Bearer']
            ],
            'access_token' => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The access token as issued by the authorization server.',
                'required'    => true
            ],
            'expires_in'   => [
                'type'        => Util::TYPE_INTEGER,
                'description' => 'the duration of time in seconds the access token is granted for.'
            ],
            'refresh_token' => [
                'type'        => Util::TYPE_STRING,
                'description' => 'The token that should be used to refresh the access token when it is expired.'
            ]
        ]);
        $this->registerErrorResponse($api, $operation, 400, 'Returned when the request data is not valid');
        $this->registerErrorResponse($api, $operation, 401, 'Returned when the server denied the request');
        $this->registerErrorResponse($api, $operation, 500, 'Returned when an unexpected error occurs');
    }

    private function registerTokenOptionsPath(OA\OpenApi $api, OA\PathItem $path): void
    {
        $operation = Util::createChildItem(OA\Options::class, $path, 'options');
        $path->options = $operation;
        $operation->operationId = 'oauthtoken-options';
        $operation->summary = 'Get OAuth 2.0 access token options';
        $operation->description = 'Get communication options for a resource';
        $operation->tags = ['oauth'];
        $operation->responses = [];
        $this->registerResponse($api, $operation, 200, 'Returned when successful');
        $this->registerErrorResponse($api, $operation, 500, 'Returned when an unexpected error occurs');
    }

    private function registerRequestBody(OA\OpenApi $api, OA\Operation $operation, array $model): void
    {
        $operation->requestBody = Util::createRequestBodyRef(
            $operation,
            $this->requestBodyStorage->registerRequestBody(
                $api,
                'application/json',
                $this->registerModel($api, $model, ModelNameUtil::buildModelName('oauthTokenRequest'))
            )->request
        );
    }

    private function registerResponse(
        OA\OpenApi $api,
        OA\Operation $operation,
        int $statusCode,
        string $description,
        ?array $model = null
    ): void {
        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $operation->responses[] = Util::createResponseRef(
            $operation,
            $statusCode,
            $this->responseStorage->registerResponse(
                $api,
                $description,
                'application/json',
                $model
                    ? $this->registerModel($api, $model, ModelNameUtil::buildModelName('oauthTokenResponse'))
                    : null
            )->response
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
                ->registerErrorResponse($api, $statusCode, $description, 'application/json', self::OAUTH_FAILURE)
                ->response
        );
    }

    private function registerModel(OA\OpenApi $api, array $model, string $modelName): string
    {
        $schema = $this->schemaStorage->findSchema($api, $modelName);
        if (null === $schema) {
            $schema = $this->schemaStorage->addSchema($api, $modelName);
            $schema->schema = $modelName;
            $properties = [];
            $requiredProperties = [];
            foreach ($model as $name => $item) {
                $properties[] = $this->createProperty(
                    $api,
                    $modelName,
                    $schema,
                    $name,
                    $item['type'],
                    $item['description'] ?? null,
                    $item['values'] ?? null
                );
                if ($item['required'] ?? false) {
                    $requiredProperties[] = $name;
                }
            }
            $schema->properties = $properties;
            $schema->required = $requiredProperties;
        }

        return $schema->schema;
    }

    private function registerFailureObject(OA\OpenApi $api): void
    {
        $schema = $this->schemaStorage->findSchema($api, self::OAUTH_FAILURE);
        if (null === $schema) {
            $schema = $this->schemaStorage->addSchema($api, self::OAUTH_FAILURE);
            $schema->type = Util::TYPE_OBJECT;
            $schema->required = ['error'];

            $messageProperty = Util::createStringProperty(
                $schema,
                'message',
                'A human-readable text providing additional information about the error.'
            );
            $messageProperty->deprecated = true;

            $schema->properties = [
                Util::createStringProperty(
                    $schema,
                    'error',
                    'An error code.'
                ),
                Util::createStringProperty(
                    $schema,
                    'error_description',
                    'A human-readable text providing additional information about the error.'
                ),
                Util::createStringProperty(
                    $schema,
                    'hint',
                    'A hint that may help to solve the error.'
                ),
                $messageProperty
            ];
        }
    }

    private function createProperty(
        OA\OpenApi $api,
        string $modelName,
        OA\AbstractAnnotation $parent,
        string $name,
        string $type,
        ?string $description,
        ?array $values
    ): OA\Property {
        $prop = Util::createChildItem(OA\Property::class, $parent);
        $prop->property = $name;
        $this->dataTypeDescribeHelper->registerPropertyType($api, $modelName, $prop, $type);
        if ($description) {
            $prop->description = $description;
        }
        if ($values) {
            $prop->enum = $values;
        }

        return $prop;
    }
}
