<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ApiFeatureCheckerTest extends TestCase
{
    private FeatureChecker&MockObject $featureChecker;
    private ApiFeatureChecker $apiFeatureChecker;

    #[\Override]
    protected function setUp(): void
    {
        $this->featureChecker = $this->createMock(FeatureChecker::class);

        $this->apiFeatureChecker = new ApiFeatureChecker($this->featureChecker);
    }

    public function testIsEnabledByClientOwnerClassForBackendEnabled(): void
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClientOwnerClass(User::class));
    }

    public function testIsEnabledByClientOwnerClassForBackendDisabled(): void
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClientOwnerClass(User::class));
    }

    public function testIsEnabledByClientOwnerClassForFrontendEnabled(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClientOwnerClass(CustomerUser::class));
    }

    public function testIsEnabledByClientOwnerClassForFrontendDisabled(): void
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            self::markTestSkipped('can be tested only with CustomerBundle');
        }

        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClientOwnerClass(CustomerUser::class));
    }

    public function testIsEnabledByClientForBackendEnabled(): void
    {
        $client = new Client();
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsEnabledByClientForBackendDisabled(): void
    {
        $client = new Client();
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsEnabledByClientForFrontendEnabled(): void
    {
        $client = new Client();
        $client->setFrontend(true);
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsEnabledByClientForFrontendDisabled(): void
    {
        $client = new Client();
        $client->setFrontend(true);
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsBackendApiEnabledForEnabled(): void
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isBackendApiEnabled());
    }

    public function testIsBackendApiEnabledForDisabled(): void
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isBackendApiEnabled());
    }

    public function testIsFrontendApiEnabledForEnabled(): void
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isFrontendApiEnabled());
    }

    public function testIsFrontendApiEnabledForDisabled(): void
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isFrontendApiEnabled());
    }
}
