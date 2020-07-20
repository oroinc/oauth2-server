<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use League\OAuth2\Server\Exception\OAuthServerException;
use Oro\Bundle\OAuth2ServerBundle\Security\OAuthUserChecker;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OAuthUserCheckerTest extends \PHPUnit\Framework\TestCase
{
    /** @var UserCheckerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $userChecker;

    /** @var OAuthUserChecker */
    private $oauthUserChecker;

    protected function setUp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())
            ->method('trans')
            ->with(self::anything(), self::anything(), 'security')
            ->willReturnCallback(
                function ($string) {
                    return '__' . $string . '_translated_';
                }
            );
        $this->userChecker = $this->createMock(UserCheckerInterface::class);

        $this->oauthUserChecker = new OAuthUserChecker($this->userChecker, $translator);
    }

    public function testCheckUserWithInvalidUserShouldThrowException()
    {
        $user = $this->createMock(UserInterface::class);
        $this->userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->with($user)
            ->willThrowException(new DisabledException());

        try {
            $this->oauthUserChecker->checkUser($user);
            $this->fail('Expencted ' . OAuthServerException::class);
        } catch (OAuthServerException $e) {
            $this->assertEquals('invalid_grant', $e->getErrorType());
            $this->assertEquals(10, $e->getCode());
            $this->assertEquals('__Account is disabled._translated_', $e->getHint());
        }
    }

    public function testCheckUserWithValidUserShouldNotThrowException()
    {
        $user = $this->createMock(UserInterface::class);
        $this->userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->with($user);

        $this->oauthUserChecker->checkUser($user);
    }
}
