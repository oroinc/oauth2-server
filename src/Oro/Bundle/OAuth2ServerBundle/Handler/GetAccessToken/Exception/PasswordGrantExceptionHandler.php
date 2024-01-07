<?php

namespace Oro\Bundle\OAuth2ServerBundle\Handler\GetAccessToken\Exception;

use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\FailedUserOAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Oro\Bundle\UserBundle\Exception\BadCredentialsException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

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

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ClientManager            $clientManager,
        private FrontendHelper|null      $frontendHelper = null,
        private OAuth2Authenticator      $oAuth2Authenticator,
        private UserProviderInterface    $userProvider,
    ) {
    }

    public function handle(ServerRequestInterface $request, OAuthServerException $exception): void
    {
        $parameters = $request->getParsedBody();
        if ('password' !== $parameters['grant_type']) {
            return;
        }

        $username  = $request->getParsedBody()['username'] ?? '';

        $token = new FailedUserOAuth2Token($username);
        $token->setAttributes($parameters);

        $authenticationException = $this->getEventException($exception);
        $authenticationException->setToken($token);

        $this->emulateRequestInFrontendHelper($parameters);

        $request = new Request();
        $request->attributes->set(Security::LAST_USERNAME, $username);
        $request->attributes->set('user', $this->getUser($username));

        try {
            $this->eventDispatcher->dispatch(
                new LoginFailureEvent(
                    $authenticationException,
                    $this->oAuth2Authenticator,
                    $request,
                    null,
                    'firewallName',
                )
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

    private function getUser(string $username): ?UserInterface
    {
        try {
            return $this->userProvider->loadUserByIdentifier($username);
        } catch (Exception $exception) {
            return null;
        }
    }
}
