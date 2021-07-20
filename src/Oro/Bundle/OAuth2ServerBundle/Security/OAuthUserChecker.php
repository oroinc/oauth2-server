<?php

namespace Oro\Bundle\OAuth2ServerBundle\Security;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks whether an user can log in to the system.
 */
class OAuthUserChecker
{
    /** @var UserCheckerInterface */
    private $userChecker;

    /** @var TranslatorInterface */
    private $translator;

    public function __construct(UserCheckerInterface $userChecker, TranslatorInterface $translator)
    {
        $this->userChecker = $userChecker;
        $this->translator = $translator;
    }

    /**
     * Checks whether the given user can log in to the system.
     *
     * @throws OAuthServerException if the given user cannot log in to the system
     */
    public function checkUser(UserInterface $user): void
    {
        try {
            $this->userChecker->checkPreAuth($user);
            $this->userChecker->checkPostAuth($user);
        } catch (AuthenticationException $e) {
            throw OAuthServerException::invalidGrant(
                $this->translator->trans($e->getMessageKey(), $e->getMessageData(), 'security')
            );
        }
    }
}
