<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitorManager;
use Oro\Bundle\CustomerBundle\Security\VisitorIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\ExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\League\AuthCodeGrantUserIdentifierUtil;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorAccessTokenParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
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
            LoggerInterface::class,
            ClientManager::class,
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
        ServerRequestInterface $serverRequest,
        SymfonyRequest $request
    ): ResponseInterface|SymfonyResponse {
        try {
            $authServer = $this->getAuthorizationServer();
            $authRequest = $authServer->validateAuthorizationRequest($serverRequest);

            $client = $this->getClient($request->get('client_id'));

            if ('plain' === $authRequest->getCodeChallengeMethod() && !$client->isPlainTextPkceAllowed()) {
                return OAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Plain code challenge method is not allowed for this client'
                )->generateHttpResponse(new Response());
            }

            if (null === $client || ($client->isFrontend() !== ('frontend' === $type))) {
                throw $this->createNotFoundException();
            }

            if ($request->getMethod() === 'POST') {
                $isAccessGranted = $request->request->get('grantAccess') === 'true';

                return $this->processAuthorization(
                    $authServer,
                    $isAccessGranted,
                    $authRequest,
                    $client,
                    $isAccessGranted ? $this->getVisitorSessionId($request) : null
                );
            }

            if ($client->isSkipAuthorizeClientAllowed()) {
                return $this->processAuthorization(
                    $authServer,
                    true,
                    $authRequest,
                    $client,
                    $this->getVisitorSessionId($request)
                );
            }
        } catch (OAuthServerException $exception) {
            return $this->handleException($serverRequest, $exception);
        }

        $template = 'frontend' === $type
            ? '@OroOAuth2Server/Security/authorize_frontend.html.twig'
            : '@OroOAuth2Server/Security/authorize.html.twig';

        return $this->render(
            $template,
            ['appName' => $client->getName()]
        );
    }

    /**
     * Processes a storefront visitor authorization.
     */
    public function authorizeVisitorAction(
        ServerRequestInterface $serverRequest,
        SymfonyRequest $request
    ): ResponseInterface {
        try {
            $authServer = $this->getAuthorizationServer();
            $authRequest = $authServer->validateAuthorizationRequest($serverRequest);

            $client = $this->getClient($request->get('client_id'));

            if ('plain' === $authRequest->getCodeChallengeMethod() && !$client->isPlainTextPkceAllowed()) {
                return OAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Plain code challenge method is not allowed for this client'
                )->generateHttpResponse(new Response());
            }

            if (null === $client || !$client->isFrontend()) {
                throw $this->createNotFoundException();
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
        Client $client,
        ?string $visitorSessionId = null
    ): ResponseInterface {
        $loggedUser = $this->getUser();
        $user = new UserEntity();
        $user->setIdentifier(
            AuthCodeGrantUserIdentifierUtil::encodeIdentifier($loggedUser->getUserIdentifier(), $visitorSessionId)
        );
        $authRequest->setUser($user);
        $authRequest->setAuthorizationApproved($isAuthorized);

        $this->container->get(AuthorizeClientHandler::class)->handle($client, $loggedUser, $isAuthorized);

        return $authServer->completeAuthorizationRequest($authRequest, new Response());
    }

    private function processVisitorAuthorization(
        AuthorizationServer $authServer,
        AuthorizationRequest $authRequest
    ): ResponseInterface {
        if (!$this->container->has(CustomerVisitorManager::class)) {
            throw OAuthServerException::serverError('the customer visitor manager does not exist.');
        }

        /** @var CustomerVisitorManager $customerVisitorManager */
        $customerVisitorManager = $this->container->get(CustomerVisitorManager::class);
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
        $this->container->get(ExceptionHandler::class)->handle($serverRequest, $exception);

        return $exception->generateHttpResponse(new Response());
    }

    private function getAuthorizationServer(): AuthorizationServer
    {
        try {
            return $this->container->get(AuthorizationServer::class);
        } catch (\LogicException $e) {
            $this->container->get(LoggerInterface::class)->warning($e->getMessage(), ['exception' => $e]);

            throw CryptKeyNotFoundException::create($e);
        }
    }

    private function getClient(string $clientId): ?Client
    {
        return $this->container->get(ClientManager::class)->getClient($clientId);
    }

    private function getVisitorSessionId(SymfonyRequest $request): ?string
    {
        $visitorAccessToken = $request->get('visitor_access_token');
        if (!$visitorAccessToken) {
            return null;
        }

        if (!$this->container->has(VisitorAccessTokenParser::class)) {
            throw OAuthServerException::serverError('the visitor access token parser does not exist.');
        }

        /** @var VisitorAccessTokenParser $customerVisitorManager */
        $visitorAccessTokenParser = $this->container->get(VisitorAccessTokenParser::class);

        return $visitorAccessTokenParser->getVisitorSessionId($visitorAccessToken);
    }
}
