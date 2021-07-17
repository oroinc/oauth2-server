<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security\Firewall;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

/**
 * The security firewall listener that detects request with OAuth 2.0 authorization header.
 */
class OAuth2Listener implements ListenerInterface
{
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var AuthenticationManagerInterface */
    private $authenticationManager;

    /** @var HttpMessageFactoryInterface */
    private $httpMessageFactory;

    /** @var string */
    private $providerKey;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        HttpMessageFactoryInterface $httpMessageFactory,
        string $providerKey
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->providerKey = $providerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(GetResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->headers->has('Authorization')
            || !preg_match('/^(?:\s+)?Bearer\s/', $request->headers->get('Authorization'))
        ) {
            return;
        }

        $token = new OAuth2Token();
        $token->setAttribute(
            OAuth2Token::REQUEST_ATTRIBUTE,
            $this->httpMessageFactory->createRequest($request)
        );
        $token->setAttribute(OAuth2Token::PROVIDER_KEY_ATTRIBUTE, $this->providerKey);

        $this->tokenStorage->setToken($this->authenticationManager->authenticate($token));
    }
}
