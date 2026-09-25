<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\OpenApi\Describer;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ErrorResponseStorage;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Storage\ResponseStorage;
use Oro\Bundle\ApiBundle\ApiDoc\OpenApi\Util;
use Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer\OAuthDescriber;
use Oro\Bundle\OAuth2ServerBundle\OpenApi\Describer\OAuthVisitorAuthorizationDescriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OAuthVisitorAuthorizationDescriberTest extends TestCase
{
    private const string VISITOR_AUTHORIZATION_ROUTE = 'oro_oauth2_server_frontend_authenticate_visitor';
    private const string VISITOR_AUTHORIZATION_URL = '/oauth2-token/authorize-visitor';
    private const string TOKEN_URL = '/oauth2-token';

    private UrlGeneratorInterface&MockObject $urlGenerator;

    private OAuthVisitorAuthorizationDescriber $describer;

    #[\Override]
    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->describer = new OAuthVisitorAuthorizationDescriber($this->urlGenerator);
        $this->describer->setResponseStorage(new ResponseStorage());
        $this->describer->setErrorResponseStorage(new ErrorResponseStorage());
    }

    public function testDescribeRegistersVisitorAuthorizationPath(): void
    {
        $api = Util::createItem(OA\OpenApi::class, Util::createContext([], null));

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(self::VISITOR_AUTHORIZATION_ROUTE)
            ->willReturn(self::VISITOR_AUTHORIZATION_URL);

        $this->describer->describe($api, []);

        self::assertCount(1, $api->paths);
        $path = $api->paths[0];
        self::assertSame(self::VISITOR_AUTHORIZATION_URL, $path->path);

        $operation = $path->get;
        self::assertInstanceOf(OA\Get::class, $operation);
        self::assertSame('oauthtoken-authorize-visitor-get', $operation->operationId);
        self::assertSame('Get OAuth 2.0 authorization code for a visitor', $operation->summary);
        self::assertSame(
            'Obtain the OAuth 2.0 authorization code for a storefront visitor (guest).',
            $operation->description
        );
        self::assertSame(['oauth'], $operation->tags);

        self::assertCount(5, $operation->parameters);
        [$responseType, $clientId, $redirectUri, $codeChallenge, $codeChallengeMethod] = $operation->parameters;
        self::assertSame('response_type', $responseType->name);
        self::assertSame('query', $responseType->in);
        self::assertSame('The response type.', $responseType->description);
        self::assertTrue($responseType->required);
        self::assertSame(Util::TYPE_STRING, $responseType->schema->type);
        self::assertSame(['code'], $responseType->schema->enum);
        self::assertSame('client_id', $clientId->name);
        self::assertTrue($clientId->required);
        self::assertSame(Util::TYPE_STRING, $clientId->schema->type);
        self::assertTrue(Generator::isDefault($clientId->schema->enum));
        self::assertSame('redirect_uri', $redirectUri->name);
        self::assertTrue(Generator::isDefault($redirectUri->required));
        self::assertSame('code_challenge', $codeChallenge->name);
        self::assertTrue(Generator::isDefault($codeChallenge->required));
        self::assertSame('code_challenge_method', $codeChallengeMethod->name);
        self::assertTrue(Generator::isDefault($codeChallengeMethod->required));
        self::assertSame(['plain', 'S256'], $codeChallengeMethod->schema->enum);

        self::assertCount(4, $operation->responses);
        [$found, $badRequest, $unauthorized, $serverError] = $operation->responses;
        self::assertSame(302, $found->response);
        self::assertSame('#/components/responses/empty', $found->ref);
        self::assertSame(400, $badRequest->response);
        self::assertSame('#/components/responses/err400_1', $badRequest->ref);
        self::assertSame(401, $unauthorized->response);
        self::assertSame('#/components/responses/err401_1', $unauthorized->ref);
        self::assertSame(500, $serverError->response);
        self::assertSame('#/components/responses/err500_1', $serverError->ref);

        self::assertCount(4, $api->components->responses);
        self::assertSame('Returned when successful', $api->components->responses[0]->description);
        self::assertSame(
            Util::getSchemaRefPath(OAuthDescriber::OAUTH_FAILURE),
            $api->components->responses[1]->content[0]->schema->ref
        );
    }

    public function testDescribeKeepsAlreadyDescribedPaths(): void
    {
        $api = Util::createItem(OA\OpenApi::class, Util::createContext([], null));
        $tokenPath = Util::createChildItem(OA\PathItem::class, $api, self::TOKEN_URL);
        $tokenPath->path = self::TOKEN_URL;
        $api->paths = [$tokenPath];

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(self::VISITOR_AUTHORIZATION_ROUTE)
            ->willReturn(self::VISITOR_AUTHORIZATION_URL);

        $this->describer->describe($api, []);

        self::assertCount(2, $api->paths);
        self::assertSame($tokenPath, $api->paths[0]);
        self::assertSame(self::VISITOR_AUTHORIZATION_URL, $api->paths[1]->path);
    }
}
