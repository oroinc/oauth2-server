<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\League\Exception\CryptKeyNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Zend\Diactoros\Response;

/**
 * The controller that implement OAuth 2.0 authorization server entry point.
 */
class AuthorizationTokenController extends Controller
{
    /**
     * Gets OAuth 2.0 access token.
     *
     * @param ServerRequestInterface $serverRequest
     *
     * @return ResponseInterface
     */
    public function tokenAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $serverResponse = new Response();

        try {
            return $this->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
        } catch (OAuthServerException $e) {
            $this->getLogger()->info($e->getMessage(), ['exception' => $e]);

            return $e->generateHttpResponse($serverResponse);
        }
    }

    /**
     * @return AuthorizationServer
     */
    private function getAuthorizationServer(): AuthorizationServer
    {
        try {
            return $this->get('oro_oauth2_server.league.authorization_server');
        } catch (\LogicException $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            throw CryptKeyNotFoundException::create($e);
        }
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        return $this->get('logger');
    }
}
