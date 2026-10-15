<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\ClientPostLogoutRedirectUris;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\ClientPostLogoutRedirectUrisValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class ClientPostLogoutRedirectUrisValidatorTest extends ConstraintValidatorTestCase
{
    #[\Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ClientPostLogoutRedirectUrisValidator();
    }

    public function testInvalidConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(new Client(), new NotBlank());
    }

    public function testInvalidValue(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(new \stdClass(), new ClientPostLogoutRedirectUris());
    }

    public function testClientWithoutGrants(): void
    {
        $client = new Client();
        $client->setLogoutAllowed(true);

        $this->validator->validate($client, new ClientPostLogoutRedirectUris());

        $this->assertNoViolation();
    }

    public function testClientWithoutAuthorizationCodeGrant(): void
    {
        $client = new Client();
        $client->setGrants(['client_credentials']);
        $client->setLogoutAllowed(true);

        $this->validator->validate($client, new ClientPostLogoutRedirectUris());

        $this->assertNoViolation();
    }

    public function testLogoutNotAllowedWithoutPostLogoutRedirectUris(): void
    {
        $client = new Client();
        $client->setGrants(['authorization_code']);

        $this->validator->validate($client, new ClientPostLogoutRedirectUris());

        $this->assertNoViolation();
    }

    public function testLogoutAllowedWithPostLogoutRedirectUris(): void
    {
        $client = new Client();
        $client->setGrants(['authorization_code']);
        $client->setLogoutAllowed(true);
        $client->setPostLogoutRedirectUris(['https://example.com/']);

        $this->validator->validate($client, new ClientPostLogoutRedirectUris());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider emptyPostLogoutRedirectUrisProvider
     */
    public function testLogoutAllowedWithoutPostLogoutRedirectUris(?array $postLogoutRedirectUris): void
    {
        $client = new Client();
        $client->setGrants(['authorization_code']);
        $client->setLogoutAllowed(true);
        $client->setPostLogoutRedirectUris($postLogoutRedirectUris);

        $constraint = new ClientPostLogoutRedirectUris();
        $this->validator->validate($client, $constraint);

        $this->buildViolation($constraint->message)
            ->atPath('property.path.postLogoutRedirectUris')
            ->assertRaised();
    }

    public static function emptyPostLogoutRedirectUrisProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
    }
}
