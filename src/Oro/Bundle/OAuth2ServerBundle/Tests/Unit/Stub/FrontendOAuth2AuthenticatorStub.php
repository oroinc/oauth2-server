<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Stub;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Security\Authenticator\FrontendOAuth2Authenticator;
use Psr\Http\Message\ServerRequestInterface;

class FrontendOAuth2AuthenticatorStub extends FrontendOAuth2Authenticator
{
    #[\Override]
    public function getUser(Client $client, ServerRequestInterface $request): object
    {
        return parent::getUser($client, $request);
    }
}
