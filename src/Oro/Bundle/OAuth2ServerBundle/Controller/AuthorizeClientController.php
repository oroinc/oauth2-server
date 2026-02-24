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
use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Oro\Bundle\OAuth2ServerBundle\Security\VisitorAccessTokenParser;
use Oro\Bundle\UIBundle\Provider\UserAgentProviderInterface;
use Oro\Bundle\UIBundle\Tools\HtmlTagHelper;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

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
            ApiDocViewProvider::class,
            TranslatorInterface::class,
            HtmlTagHelper::class,
            UserAgentProviderInterface::class,
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

            /** @var ClientEntity $client */
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

        $parameters = [
            'appName' => $this->getAppName($client),
            'resources' => $this->getAppResources($client)
        ];

        return $client->isFrontend()
            ? ['data' => $parameters]
            : $this->render('@OroOAuth2Server/Security/authorize.html.twig', $parameters);
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

    private function getAppName(ClientEntity $client): string
    {
        return $this->getHtmlTagHelper()->sanitize($client->getName());
    }

    private function getAppResources(ClientEntity $client): string
    {
        $names = $this->getAppResourceNames($client);
        $placeholder = '{NAMES_PLACEHOLDER}';
        $result = $this->getTranslator()->trans(
            'oro.oauth2server.auth_code.authorize_api_resources',
            ['%names%' => $placeholder, '%count%' => \count($names)]
        );
        $pos = strpos($result, $placeholder);
        if (false !== $pos && '</li>' === substr($result, $pos + \strlen($placeholder), 5)) {
            $result = str_replace(
                [$placeholder, '<ul', '</ul>'],
                [implode('</li><li>', $names), '</p><ul', '</ul><p></p><p>'],
                $result
            );
        } else {
            $result = str_replace($placeholder, implode(', ', $names), $result);
        }

        return $result;
    }

    private function getAppResourceNames(ClientEntity $client): array
    {
        $apiDocViewProvider = $this->getApiDocViewProvider();
        $labels = $apiDocViewProvider->getViewLabels($client->isFrontend(), $client->getApis());
        if (!$labels) {
            return [];
        }

        $formattedNames = [];
        foreach ($labels as $name => $label) {
            if (!$label) {
                continue;
            }
            $formattedName = \sprintf('<b>%s</b>', $label);
            $description = $apiDocViewProvider->getViewDescription($name);
            if ($description) {
                if (!$client->isFrontend()) {
                    $formattedName = \sprintf(
                        '<span class="resource-name">%s'
                        . '<i class="fa-info-circle tooltip-icon" data-content="%s" data-toggle="popover"></i></span>',
                        $formattedName,
                        htmlspecialchars(
                            \sprintf('<div class="oro-popover-content">%s</div>', $description),
                            ENT_QUOTES,
                            null,
                            false
                        )
                    );
                } elseif (!$this->getUserAgentProvider()->getUserAgent()->isMobile()) {
                    $formattedName = \sprintf(
                        '<span class="resource-name" data-toggle="tooltip" title="%s">%s</span>',
                        htmlspecialchars($description, ENT_QUOTES, null, false),
                        $formattedName
                    );
                }
            } else {
                $formattedName = \sprintf('<span class="resource-name">%s</span>', $formattedName);
            }
            $formattedNames[] = $formattedName;
        }

        return $formattedNames;
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

    private function getApiDocViewProvider(): ApiDocViewProvider
    {
        return $this->container->get(ApiDocViewProvider::class);
    }

    private function getTranslator(): TranslatorInterface
    {
        return $this->container->get(TranslatorInterface::class);
    }

    private function getHtmlTagHelper(): HtmlTagHelper
    {
        return $this->container->get(HtmlTagHelper::class);
    }

    private function getUserAgentProvider(): UserAgentProviderInterface
    {
        return $this->container->get(UserAgentProviderInterface::class);
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
