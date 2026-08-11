<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler\SessionTransferContextHandlerRegistry;
use Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Token\SessionTransferTokenRedeemer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Exchanges the Session Transfer Tokens to the creates browser sessions and redirects users to their target routes.
 */
final class SessionTransferController
{
    public function __construct(
        private readonly SessionTransferTokenRedeemer $tokenRedeemer,
        private readonly SessionTransferContextHandlerRegistry $handlerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function consumeBackend(Request $request): Response
    {
        $transferToken = $this->tokenRedeemer->redeem($request->query->getString('token'));
        if ($transferToken->getClient()->isFrontend()) {
            throw new BadRequestHttpException(
                'A storefront Session Transfer Token cannot be redeemed by the back-office endpoint.'
            );
        }

        return $this->createSessionAndRedirect($request, $transferToken);
    }

    public function consumeFrontend(Request $request): Response
    {
        $transferToken = $this->tokenRedeemer->redeem($request->query->getString('token'));
        if (!$transferToken->getClient()->isFrontend()) {
            throw new BadRequestHttpException(
                'A back-office Session Transfer Token cannot be redeemed by the storefront endpoint.'
            );
        }

        return $this->createSessionAndRedirect($request, $transferToken);
    }

    private function createSessionAndRedirect(
        Request $request,
        SessionTransferToken $transferToken
    ): Response {
        $handler = $this->handlerRegistry->getHandler($transferToken);
        $handler->createSession($request, $transferToken);
        $targetUrl = $this->urlGenerator->generate(
            $transferToken->getRoute(),
            $transferToken->getRouteParameters(),
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $response = new RedirectResponse($targetUrl, Response::HTTP_SEE_OTHER);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
