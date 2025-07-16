<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Security\Authentication\Token;

use Oro\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Fixtures\Entity\ExtendedOrganization;
use Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Fixtures\Entity\ExtendedRole;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\Role;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class OAuth2TokenTest extends TestCase
{
    public function testFullyAuthenticatedToken(): void
    {
        $user = new User();
        $role = new Role();
        $organization = new Organization();
        $user->addUserRole($role);

        $token = new OAuth2Token($user, $organization);

        self::assertSame($user, $token->getUser());
        self::assertSame($organization, $token->getOrganization());
        self::assertSame([$role], $token->getRoles());
    }

    public function testGetRolesForExtendedOrganizationAndRole(): void
    {
        $organization1 = new ExtendedOrganization();
        $organization1->setId(1);
        $organization2 = new ExtendedOrganization();
        $organization2->setId(2);

        $globalRole = new ExtendedRole('global');
        $organization1Role = new ExtendedRole('organization1');
        $organization1Role->setOrganization($organization1);
        $organization2Role = new ExtendedRole('organization2');
        $organization2Role->setOrganization($organization2);

        $user = new User();
        $user->addUserRole($globalRole);
        $user->addUserRole($organization1Role);
        $user->addUserRole($organization2Role);

        $token = new OAuth2Token($user, $organization1);

        $tokenRoles = $token->getRoles();
        self::assertContains($globalRole, $tokenRoles);
        self::assertContains($organization1Role, $tokenRoles);
        self::assertNotContains($organization2Role, $tokenRoles);
    }
}
