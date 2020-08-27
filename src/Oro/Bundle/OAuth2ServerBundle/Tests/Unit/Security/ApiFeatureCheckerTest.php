<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ApiFeatureCheckerTest extends \PHPUnit\Framework\TestCase
{
    /** @var FeatureChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $featureChecker;

    /** @var ApiFeatureChecker */
    private $apiFeatureChecker;

    protected function setUp(): void
    {
        $this->featureChecker = $this->createMock(FeatureChecker::class);

        $this->apiFeatureChecker = new ApiFeatureChecker($this->featureChecker);
    }

    public function testIsEnabledByClientOwnerClassForBackendEnabled()
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClientOwnerClass(User::class));
    }

    public function testIsEnabledByClientOwnerClassForBackendDisabled()
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClientOwnerClass(User::class));
    }

    public function testIsEnabledByClientOwnerClassForFrontendEnabled()
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $this->markTestSkipped('can be tested only with installed customer portal');
        }

        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClientOwnerClass(CustomerUser::class));
    }

    public function testIsEnabledByClientOwnerClassForFrontendDisabled()
    {
        if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')) {
            $this->markTestSkipped('can be tested only with installed customer portal');
        }

        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClientOwnerClass(CustomerUser::class));
    }

    public function testIsEnabledByClientForBackendEnabled()
    {
        $client = new Client();
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsEnabledByClientForBackendDisabled()
    {
        $client = new Client();
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsEnabledByClientForFrontendEnabled()
    {
        $client = new Client();
        $client->setFrontend(true);
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsEnabledByClientForFrontendDisabled()
    {
        $client = new Client();
        $client->setFrontend(true);
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isEnabledByClient($client));
    }

    public function testIsBackendApiEnabledForEnabled()
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isBackendApiEnabled());
    }

    public function testIsBackendApiEnabledForDisabled()
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isBackendApiEnabled());
    }

    public function testIsFrontendApiEnabledForEnabled()
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(true);
        self::assertTrue($this->apiFeatureChecker->isFrontendApiEnabled());
    }

    public function testIsFrontendApiEnabledForDisabled()
    {
        $this->featureChecker->expects(self::once())
            ->method('isFeatureEnabled')
            ->with('frontend_web_api')
            ->willReturn(false);
        self::assertFalse($this->apiFeatureChecker->isFrontendApiEnabled());
    }
}
