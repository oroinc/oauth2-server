<?php

namespace Oro\Bundle\OAuth2ServerBundle\EventListener;

use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Changes templates for backoffice login routes.
 */
class OauthLoginTemplateListener
{
    private array $routes = [];

    public function __construct(
        private readonly ClientManager $clientManager
    ) {
    }

    public function addRoute(string $route): void
    {
        $this->routes[] = $route;
    }

    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();

        $route = $request->attributes->get('_route');
        if (!in_array($route, $this->routes, true)) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (!$session) {
            return;
        }
        parse_str(parse_url($session->get('_security.main.target_path'), PHP_URL_QUERY), $parameters);
        if (!array_key_exists('client_id', $parameters)) {
            return;
        }

        $templateReference = $this->getTemplateReference($request);
        $template = $templateReference->getTemplate();
        $templateReference->setTemplate(substr_replace($template, '@OroOAuth2Server', 0, strpos($template, '/')));
        $request->attributes->set('_oauth_login', true);

        $client = $this->clientManager->getClient($parameters['client_id']);
        $event->setControllerResult(array_merge($event->getControllerResult(), ['appName' => $client->getName()]));
    }

    private function getTemplateReference(Request $request): ?Template
    {
        $template = $request->attributes->get('_template');

        if ($template instanceof Template) {
            return $template;
        }

        if (is_string($template)) {
            $parsedTemplate = new Template();
            $parsedTemplate->setTemplate($template);
            $request->attributes->set('_template', $parsedTemplate);

            return $parsedTemplate;
        }

        return null;
    }
}
