<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\ExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorAccessTokenParser;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The controller that allows to authorize client during authorization code grant flow.
 */
class AuthorizeClientController extends AbstractController
{
    #[\Override]
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            AuthorizationServer::class,
            AuthorizeClientHandler::class,
            ExceptionHandler::class,
            '?' . CustomerVisitorManager::class,
            '?' . VisitorAccessTokenParser::class
        ]);
    }

    /**
     * Processes the authorize client form page.
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function authorizeAction(
        string $type,
        ServerRequestInterface $serverRequest
    ): ResponseInterface|SymfonyResponse|array {
        try {
            $authServer = $this->getAuthorizationServer();
            $authRequest = $authServer->validateAuthorizationRequest($serverRequest);

            $client = $authRequest->getClient();

            if ($client->isFrontend() !== ('frontend' === $type)) {
                throw $this->createNotFoundException();
            }

            if ('plain' === $authRequest->getCodeChallengeMethod() && !$client->isPlainTextPkceAllowed()) {
                return OAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Plain code challenge method is not allowed for this client'
                )->generateHttpResponse(new Response());
            }

            if ('POST' === $serverRequest->getMethod()) {
                $isAccessGranted = 'true' === ($serverRequest->getParsedBody()['grantAccess'] ?? null);

                return $this->processAuthorization(
                    $authServer,
                    $isAccessGranted,
                    $authRequest,
                    $client,
                    $isAccessGranted ? $this->getVisitorSessionId($serverRequest) : null
                );
            }

            if ($client->isSkipAuthorizeClientAllowed()) {
                return $this->processAuthorization(
                    $authServer,
                    true,
                    $authRequest,
                    $client,
                    $this->getVisitorSessionId($serverRequest)
                );
            }
        } catch (OAuthServerException $exception) {
            return $this->handleException($serverRequest, $exception);
        }

        return 'frontend' === $type
            ? ['data' => ['appName' => $client->getName()]]
            : $this->render(
                '@OroOAuth2Server/Security/authorize.html.twig',
                ['appName' => $client->getName()]
            );
    }

    /**
     * Processes a storefront visitor authorization.
     */
    public function authorizeVisitorAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        try {
            $authServer = $this->getAuthorizationServer();
            $authRequest = $authServer->validateAuthorizationRequest($serverRequest);

            $client = $authRequest->getClient();

            if (!$client->isFrontend()) {
                throw $this->createNotFoundException();
            }

            if ('plain' === $authRequest->getCodeChallengeMethod() && !$client->isPlainTextPkceAllowed()) {
                return OAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Plain code challenge method is not allowed for this client'
                )->generateHttpResponse(new Response());
            }

            return $this->processVisitorAuthorization($authServer, $authRequest);
        } catch (OAuthServerException $exception) {
            return $this->handleException($serverRequest, $exception);
        }
    }

    private function processAuthorization(
        AuthorizationServer $authServer,
        bool $isAuthorized,
        AuthorizationRequest $authRequest,
        ClientEntity $client,
        ?string $visitorSessionId = null
    ): ResponseInterface {
        /** @var UserInterface $loggedUser */
        $loggedUser = $this->getUser();
        $user = new UserEntity();
        $user->setIdentifier(
            AuthCodeGrantUserIdentifierUtil::encodeIdentifier($loggedUser->getUserIdentifier(), $visitorSessionId)
        );
        $authRequest->setUser($user);
        $authRequest->setAuthorizationApproved($isAuthorized);

        $this->getAuthorizeClientHandler()->handle($client, $loggedUser, $isAuthorized);

        return $authServer->completeAuthorizationRequest($authRequest, new Response());
    }

    private function processVisitorAuthorization(
        AuthorizationServer $authServer,
        AuthorizationRequest $authRequest
    ): ResponseInterface {
        $customerVisitorManager = $this->getCustomerVisitorManager();
        if (null === $customerVisitorManager) {
            throw OAuthServerException::serverError('the customer visitor manager does not exist.');
        }

        $user = new UserEntity();
        $user->setIdentifier(VisitorIdentifierUtil::encodeIdentifier($customerVisitorManager->generateSessionId()));
        $authRequest->setUser($user);
        $authRequest->setAuthorizationApproved(true);

        return $authServer->completeAuthorizationRequest($authRequest, new Response());
    }

    private function handleException(
        ServerRequestInterface $serverRequest,
        OAuthServerException $exception
    ): ResponseInterface {
        $this->getExceptionHandler()->handle($serverRequest, $exception);

        return $exception->generateHttpResponse(new Response());
    }

    private function getVisitorSessionId(ServerRequestInterface $serverRequest): ?string
    {
        $visitorAccessToken = $serverRequest->getQueryParams()['visitor_access_token']
            ?? ((array)$serverRequest->getParsedBody())['visitor_access_token']
            ?? null;
        if (!$visitorAccessToken) {
            return null;
        }

        $visitorAccessTokenParser = $this->getVisitorAccessTokenParser();
        if (null === $visitorAccessTokenParser) {
            throw OAuthServerException::serverError('the visitor access token parser does not exist.');
        }

        return $visitorAccessTokenParser->getVisitorSessionId($visitorAccessToken);
    }

    private function getAuthorizationServer(): AuthorizationServer
    {
        try {
            return $this->container->get(AuthorizationServer::class);
        } catch (\LogicException $e) {
            throw CryptKeyNotFoundException::create($e);
        }
    }

    private function getAuthorizeClientHandler(): AuthorizeClientHandler
    {
        return $this->container->get(AuthorizeClientHandler::class);
    }

    private function getExceptionHandler(): ExceptionHandler
    {
        return $this->container->get(ExceptionHandler::class);
    }

    private function getCustomerVisitorManager(): ?CustomerVisitorManager
    {
        return $this->container->has(CustomerVisitorManager::class)
            ? $this->container->get(CustomerVisitorManager::class)
            : null;
    }

    private function getVisitorAccessTokenParser(): ?VisitorAccessTokenParser
    {
        return $this->container->has(VisitorAccessTokenParser::class)
            ? $this->container->get(VisitorAccessTokenParser::class)
            : null;
    }
}
