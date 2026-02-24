<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\AuthorizeClientHandler;
use Oro\Bundle\OAuth2ServerBundle\Handler\AuthorizeClient\Exception\ExceptionHandler;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Oro\Bundle\OAuth2ServerBundle\Provider\ApiDocViewProvider;
use Oro\Bundle\UIBundle\Provider\UserAgentProviderInterface;
use Oro\Bundle\UIBundle\Tools\HtmlTagHelper;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The controller that allows to authorize client during authorization code grant flow.
 */
class AuthorizeClientController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            LoggerInterface::class,
            ClientManager::class,
            AuthorizationServer::class,
            AuthorizeClientHandler::class,
            ExceptionHandler::class,
            ApiDocViewProvider::class,
            TranslatorInterface::class,
            HtmlTagHelper::class,
            UserAgentProviderInterface::class
        ]);
    }

    /**
     * Processes the authorize client form page.
     *
     * @param string                 $type
     * @param ServerRequestInterface $serverRequest
     * @param SymfonyRequest         $request
     *
     * @return ResponseInterface|SymfonyResponse
     */
    public function authorizeAction(
        string $type,
        ServerRequestInterface $serverRequest,
        Request $request
    ) {
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
                return $this->processAuthorization(
                    $request->request->get('grantAccess') === 'true',
                    $authRequest,
                    $client
                );
            }

            if ($client->isSkipAuthorizeClientAllowed()) {
                return $this->processAuthorization(true, $authRequest, $client);
            }
        } catch (OAuthServerException $exception) {
            $this->getExceptionHandler()->handle($serverRequest, $exception);

            return $exception->generateHttpResponse(new Response());
        }

        $template = $client->isFrontend()
            ? '@OroOAuth2Server/Security/authorize_frontend.html.twig'
            : '@OroOAuth2Server/Security/authorize.html.twig';

        return $this->render(
            $template,
            ['appName' => $this->getAppName($client), 'resources' => $this->getAppResources($client)]
        );
    }

    private function processAuthorization(
        bool $isAuthorized,
        AuthorizationRequest $authRequest,
        ClientEntity $client
    ): ResponseInterface {
        $authServer = $this->getAuthorizationServer();
        /** @var UserInterface $loggedUser */
        $loggedUser = $this->getUser();
        $user = new UserEntity();
        $user->setIdentifier($loggedUser->getUserIdentifier());
        $authRequest->setUser($user);
        $authRequest->setAuthorizationApproved($isAuthorized);

        $this->getAuthorizeClientHandler()->handle(
            $this->container->get(ClientManager::class)->getClient($client->getIdentifier()),
            $loggedUser,
            $isAuthorized
        );

        return $authServer->completeAuthorizationRequest($authRequest, new Response());
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
}
