<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authenticator;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Oro\Bundle\ApiBundle\Security\FeatureDependAuthenticatorChecker;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Security\ClientOwnerScopeValidator;
use Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Stub\FrontendOAuth2AuthenticatorStub;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class FrontendOAuth2AuthenticatorTest extends TestCase
{
    private UserProviderInterface&MockObject $userProvider;
    private ClientOwnerScopeValidator&MockObject $clientOwnerScopeValidator;
    private FrontendOAuth2AuthenticatorStub $authenticator;

    #[\Override]
    protected function setUp(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->clientOwnerScopeValidator = $this->createMock(ClientOwnerScopeValidator::class);
        $this->authenticator = new FrontendOAuth2AuthenticatorStub(
            $this->createMock(LoggerInterface::class),
            $this->createMock(ClientManager::class),
            $this->createMock(ManagerRegistry::class),
            $this->userProvider,
            $this->createMock(AuthorizationValidatorInterface::class),
            $this->createMock(HttpMessageFactoryInterface::class),
            $this->createMock(FeatureDependAuthenticatorChecker::class),
            'test'
        );
    }

    public function testGetUserWhenUserIsOutsideOfClientOwnerScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isFrontend')
            ->willReturn(true);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('oauth_user_id')
            ->willReturn('user@example.org');
        $this->clientOwnerScopeValidator->expects(self::once())
            ->method('isValid')
            ->with(self::identicalTo($client), 'user@example.org')
            ->willReturn(false);
        $this->authenticator->setClientOwnerScopeValidator($this->clientOwnerScopeValidator);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The user is outside of the OAuth application owner scope.');

        $this->authenticator->getUser($client, $request);
    }

    public function testGetUserWithoutClientOwnerScopeValidator(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('isFrontend')
            ->willReturn(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'The client owner scope validator must be configured for frontend OAuth applications.'
        );

        $this->authenticator->getUser($client, $this->createMock(ServerRequestInterface::class));
    }

    public function testGetUserDelegatesToParentAuthenticator(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(2))
            ->method('isFrontend')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('getOwnerEntityClass')
            ->willReturn(null);
        $client->expects(self::once())
            ->method('getOwnerEntityId')
            ->willReturn(null);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::exactly(2))
            ->method('getAttribute')
            ->with('oauth_user_id')
            ->willReturn('user@example.org');
        $this->clientOwnerScopeValidator->expects(self::once())
            ->method('isValid')
            ->with(self::identicalTo($client), 'user@example.org')
            ->willReturn(true);
        $this->authenticator->setClientOwnerScopeValidator($this->clientOwnerScopeValidator);

        $user = $this->createMock(CustomerUser::class);
        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->with(CustomerUser::class)
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('user@example.org')
            ->willReturn($user);

        self::assertSame($user, $this->authenticator->getUser($client, $request));
    }

    public function testGetUserForBackendClientDoesNotValidateOwnerScope(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(2))
            ->method('isFrontend')
            ->willReturn(false);
        $client->expects(self::once())
            ->method('getOwnerEntityClass')
            ->willReturn(null);
        $client->expects(self::once())
            ->method('getOwnerEntityId')
            ->willReturn(null);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('oauth_user_id')
            ->willReturn('user@example.org');
        $this->clientOwnerScopeValidator->expects(self::never())
            ->method('isValid');
        $this->authenticator->setClientOwnerScopeValidator($this->clientOwnerScopeValidator);

        $user = $this->createMock(User::class);
        $this->userProvider->expects(self::once())
            ->method('supportsClass')
            ->with(User::class)
            ->willReturn(true);
        $this->userProvider->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('user@example.org')
            ->willReturn($user);

        self::assertSame($user, $this->authenticator->getUser($client, $request));
    }
}
