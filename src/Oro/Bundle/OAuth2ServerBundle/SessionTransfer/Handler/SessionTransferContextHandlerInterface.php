<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\SessionTransfer\Handler;

use Oro\Bundle\OAuth2ServerBundle\Entity\SessionTransferToken;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates a session for a supported Session Transfer Token context.
 */
interface SessionTransferContextHandlerInterface
{
    public function supports(SessionTransferToken $transferToken): bool;

    public function createSession(Request $request, SessionTransferToken $transferToken): void;
}
