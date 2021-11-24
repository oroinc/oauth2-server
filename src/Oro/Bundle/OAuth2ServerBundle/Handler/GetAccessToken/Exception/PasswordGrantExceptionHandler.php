<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\FailedUserOAuth2Token;
use Oro\Bundle\UserBundle\Exception\BadCredentialsException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The handler that dispatches the "security.authentication.failure" event in case of an exception occurred
 * when getting an access token for the password grant.
 */
class PasswordGrantExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @see \League\OAuth2\Server\Exception\OAuthServerException::invalidCredentials
     * @see \League\OAuth2\Server\Exception\OAuthServerException::invalidGrant
     */
    private static $badCredentialsExceptionCodes = [6, 10];

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var ClientManager */
    private $clientManager;

    /** @var FrontendHelper|null */
    private $frontendHelper;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ClientManager $clientManager,
        FrontendHelper $frontendHelper = null
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->clientManager = $clientManager;
        $this->frontendHelper = $frontendHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        $parameters = $request->getParsedBody();
        if ('password' !== $parameters['grant_type']) {
            return;
        }

        $token = new FailedUserOAuth2Token($parameters['username']);
        $token->setAttributes($parameters);

        $authenticationException = $this->getEventException($exception);
        $authenticationException->setToken($token);

        $this->emulateRequestInFrontendHelper($parameters);
        try {
            $this->eventDispatcher->dispatch(
                new AuthenticationFailureEvent($token, $authenticationException),
                AuthenticationEvents::AUTHENTICATION_FAILURE
            );
        } finally {
            $this->restoreFrontendHelper();
        }
    }

    private function getEventException(\Exception $exception): AuthenticationException
    {
        $exceptionCode = $exception->getCode();
        if ($exception->getPrevious() instanceof AuthenticationException) {
            $authenticationException = $exception->getPrevious();
        } elseif (in_array($exceptionCode, self::$badCredentialsExceptionCodes, true)) {
            $authenticationException = new BadCredentialsException(
                $exception->getMessage(),
                $exceptionCode,
                $exception
            );
        } else {
            $authenticationException = new AuthenticationException(
                $exception->getMessage(),
                $exceptionCode,
                $exception
            );
        }

        return $authenticationException;
    }

    private function emulateRequestInFrontendHelper(array $parameters): void
    {
        if ($this->frontendHelper) {
            /** @var Client $client */
            $client = $this->clientManager->getClient($parameters['client_id']);

            if ($client && $client->isFrontend()) {
                $this->frontendHelper->emulateFrontendRequest();
            } else {
                $this->frontendHelper->emulateBackendRequest();
            }
        }
    }

    private function restoreFrontendHelper(): void
    {
        if ($this->frontendHelper) {
            $this->frontendHelper->resetRequestEmulation();
        }
    }
}
