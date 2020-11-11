<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\UserEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Zend\Diactoros\Response;

/**
 * The controller that allows to authorize client during authorization code grant flow.
 */
class AuthorizeClientController extends Controller
{
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

            $client = $this->getClient($request->get('client_id'));
            if (null === $client || ($client->isFrontend() !== ('frontend' === $type))) {
                throw $this->createNotFoundException();
            }

            if ($request->getMethod() === 'POST') {
                if (!$this->isCsrfTokenValid('authorize_client', $request->request->get('_csrf_token'))) {
                    throw OAuthServerException::invalidRequest('_csrf_token');
                }

                $loggedUser = $this->getUser();
                $user = new UserEntity();
                $user->setIdentifier($loggedUser->getUsername());
                $authRequest->setUser($user);
                $isAuthorized = $request->request->get('grantAccess') === 'true';
                $authRequest->setAuthorizationApproved($isAuthorized);

                $this->get('oro_oauth2_server.handler.authorize_client.handler')
                    ->handle($client, $loggedUser, $isAuthorized);
                return $authServer->completeAuthorizationRequest($authRequest, new Response());
            }
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new Response());
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
     * @return AuthorizationServer
     */
    private function getAuthorizationServer(): AuthorizationServer
    {
        try {
            return $this->get('oro_oauth2_server.league.authorization_server');
        } catch (\LogicException $e) {
            $this->get('logger')->warning($e->getMessage(), ['exception' => $e]);

            throw CryptKeyNotFoundException::create($e);
        }
    }

    /**
     * @param string $clientId
     *
     * @return Client|null
     */
    private function getClient(string $clientId): ?Client
    {
        return $this->get('doctrine')
            ->getRepository(Client::class)
            ->findOneBy(['identifier' => $clientId]);
    }
}