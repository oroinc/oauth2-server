<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The controller that is used to log-in user during Authorization Code grant flow.
 */
class LoginController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            CsrfTokenManagerInterface::class,
            AuthenticationUtils::class,
            'doctrine' => ManagerRegistry::class
        ]);
    }

    /**
     * @param string  $type
     * @param Request $request
     *
     * @return Response
     */
    public function loginAction(string $type, Request $request): Response
    {
        if ('frontend' === $type) {
            $sessionParameterName = '_security.oauth2_frontend_authorization_authenticate.target_path';
            $template = '@OroOAuth2Server/Security/login_frontend.html.twig';
        } else {
            $sessionParameterName = '_security.oauth2_authorization_authenticate.target_path';
            $template = '@OroOAuth2Server/Security/login.html.twig';
        }
        $session = $request->getSession();

        if (null === $session || !$session->has($sessionParameterName)) {
            throw $this->createNotFoundException();
        }

        parse_str(parse_url($session->get($sessionParameterName), PHP_URL_QUERY), $parameters);

        if (empty($parameters['client_id'])) {
            throw $this->createNotFoundException();
        }

        $client = $this->getClient($parameters['client_id']);
        if (null === $client || ($client->isFrontend() !== ('frontend' === $type))) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            $template,
            [
                'last_username' => $this->get(AuthenticationUtils::class)->getLastUsername(),
                'error'         => $this->get(AuthenticationUtils::class)->getLastAuthenticationError(),
                'csrf_token'    => $this->get(CsrfTokenManagerInterface::class)->getToken('authenticate')->getValue(),
                'appName'       => $client->getName()
            ]
        );
    }

    public function checkAction(): void
    {
        throw new \RuntimeException(
            'You must configure the check path to be handled by the firewall ' .
            'using organization-form-login in your security firewall configuration.'
        );
    }

    /**
     * @param string $clientId
     *
     * @return Client|null
     */
    private function getClient(string $clientId): ?Client
    {
        return $this->get('oro_oauth2_server.client_manager')->getClient($clientId);
    }
}
