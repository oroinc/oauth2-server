<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Grant;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Grant\SessionTransferGrant;
use Oro\Bundle\OAuth2ServerBundle\League\ResponseType\SessionTransferTokenResponse;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\IssuedSessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Model\SessionTransferTarget;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Route\SessionTransferRouteValidator;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\SessionTransferSubjectResolver;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\SessionTransferTokenManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class SessionTransferGrantTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        self::assertSame(Client::SESSION_TRANSFER, $this->createGrant()->getIdentifier());
    }

    public function testRespondToAccessTokenRequest(): void
    {
        $route = \str_repeat('r', 256);
        $organization = new Organization();
        ReflectionUtil::setId($organization, 10);
        $client = (new Client())
            ->setIdentifier('client-id')
            ->setOrganization($organization)
            ->setSessionTransferAllowed(true);
        $leagueClient = new ClientEntity();
        $leagueClient->setIdentifier('client-id');
        $sourceAccessToken = new AccessToken(
            'source-access-token-id',
            new \DateTime('+1 hour'),
            [],
            $client,
            'user@example.com'
        );
        $target = new SessionTransferTarget($route, ['id' => 10]);
        $issuedToken = new IssuedSessionTransferToken(
            'stt_token',
            60,
            new SessionTransferToken()
        );
        $request = (new ServerRequest('POST', '/oauth2-token'))
            ->withParsedBody([
                'grant_type' => Client::SESSION_TRANSFER,
                'client_id' => 'client-id',
                'subject_token' => 'source-access-token',
                'route' => $route,
                'route_parameters' => '{"id":10}',
            ]);

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $clientRepository->expects(self::once())
            ->method('validateClient')
            ->with('client-id', null, Client::SESSION_TRANSFER)
            ->willReturn(true);
        $clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with('client-id')
            ->willReturn($leagueClient);
        $authorizationValidator = $this->createMock(AuthorizationValidatorInterface::class);
        $authorizationValidator->expects(self::once())
            ->method('validateAuthorization')
            ->with(self::callback(static function (ServerRequestInterface $request): bool {
                return 'Bearer source-access-token' === $request->getHeaderLine('Authorization');
            }))
            ->willReturn($request->withAttribute('oauth_access_token_id', 'source-access-token-id'));
        $clientManager = $this->createMock(ClientManager::class);
        $clientManager->expects(self::once())->method('getClient')->with('client-id')->willReturn($client);
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['identifier' => 'source-access-token-id'])
            ->willReturn($sourceAccessToken);
        $objectManager = $this->createMock(ObjectManager::class);
        $objectManager->expects(self::once())
            ->method('getRepository')
            ->with(AccessToken::class)
            ->willReturn($repository);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with(AccessToken::class)
            ->willReturn($objectManager);
        $subjectResolver = $this->createMock(SessionTransferSubjectResolver::class);
        $subjectResolver->expects(self::once())
            ->method('resolveUserIdentifier')
            ->with($sourceAccessToken)
            ->willReturn('user@example.com');
        $routeValidator = $this->createMock(SessionTransferRouteValidator::class);
        $routeValidator->expects(self::once())
            ->method('validate')
            ->with($route, ['id' => 10], $client)
            ->willReturn($target);
        $tokenManager = $this->createMock(SessionTransferTokenManager::class);
        $ttl = new \DateInterval('PT60S');
        $tokenManager->expects(self::once())
            ->method('createToken')
            ->with($client, 'source-access-token-id', 'user@example.com', $target, $ttl)
            ->willReturn($issuedToken);
        $responsePrototype = new SessionTransferTokenResponse();
        $grant = new SessionTransferGrant(
            $authorizationValidator,
            $clientManager,
            $doctrine,
            $subjectResolver,
            $routeValidator,
            $tokenManager,
            $responsePrototype
        );
        $grant->setClientRepository($clientRepository);

        $response = $grant->respondToAccessTokenRequest(
            $request,
            $this->createMock(ResponseTypeInterface::class),
            $ttl
        );

        self::assertInstanceOf(SessionTransferTokenResponse::class, $response);
        self::assertNotSame($responsePrototype, $response);
    }

    public function testRespondToAccessTokenRequestRejectsWhitespaceRouteParameterName(): void
    {
        $request = (new ServerRequest('POST', '/oauth2-token'))
            ->withParsedBody([
                'grant_type' => Client::SESSION_TRANSFER,
                'client_id' => 'client-id',
                'subject_token' => 'source-access-token',
                'route' => 'target_route',
                'route_parameters' => [' ' => 10],
            ]);
        $client = (new Client())
            ->setIdentifier('client-id')
            ->setSessionTransferAllowed(true);
        $leagueClient = new ClientEntity();
        $leagueClient->setIdentifier('client-id');
        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $clientRepository->expects(self::once())
            ->method('validateClient')
            ->with('client-id', null, Client::SESSION_TRANSFER)
            ->willReturn(true);
        $clientRepository->expects(self::once())
            ->method('getClientEntity')
            ->with('client-id')
            ->willReturn($leagueClient);
        $clientManager = $this->createMock(ClientManager::class);
        $clientManager->expects(self::once())
            ->method('getClient')
            ->with('client-id')
            ->willReturn($client);
        $grant = new SessionTransferGrant(
            $this->createMock(AuthorizationValidatorInterface::class),
            $clientManager,
            $this->createMock(ManagerRegistry::class),
            $this->createMock(SessionTransferSubjectResolver::class),
            $this->createMock(SessionTransferRouteValidator::class),
            $this->createMock(SessionTransferTokenManager::class),
            new SessionTransferTokenResponse()
        );
        $grant->setClientRepository($clientRepository);

        try {
            $grant->respondToAccessTokenRequest(
                $request,
                $this->createMock(ResponseTypeInterface::class),
                new \DateInterval('PT60S')
            );
            self::fail('Expected an invalid request exception.');
        } catch (OAuthServerException $exception) {
            self::assertSame(3, $exception->getCode());
            self::assertSame(
                'Every route parameter must have a non-empty string name.',
                $exception->getHint()
            );
        }
    }

    private function createGrant(): SessionTransferGrant
    {
        return new SessionTransferGrant(
            $this->createMock(AuthorizationValidatorInterface::class),
            $this->createMock(ClientManager::class),
            $this->createMock(ManagerRegistry::class),
            $this->createMock(SessionTransferSubjectResolver::class),
            $this->createMock(SessionTransferRouteValidator::class),
            $this->createMock(SessionTransferTokenManager::class),
            new SessionTransferTokenResponse()
        );
    }
}
