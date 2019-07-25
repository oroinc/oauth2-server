<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\ClientOwner;
use Oro\Bundle\OAuth2ServerBundle\Validator\Constraints\ClientOwnerValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ClientOwnerValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function createValidator()
    {
        return new ClientOwnerValidator();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testInvalidConstraint()
    {
        $this->validator->validate(new Client(), new NotBlank());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testInvalidValue()
    {
        $this->validator->validate(new \stdClass(), new ClientOwner());
    }

    public function testOnEmptyGrants()
    {
        $this->validator->validate(new Client(), new ClientOwner());

        $this->assertNoViolation();
    }

    public function testOnClientGrantsAndUserClassAndId()
    {
        $constraint = new ClientOwner();
        $client = new Client();
        $client->setGrants(['client_credentials']);
        $client->setOwnerEntity(\stdClass::class, 1);

        $this->validator->validate($client, $constraint);

        $this->assertNoViolation();
    }

    public function testOnNotClientGrantsAndEmptyUserClassAndId()
    {
        $constraint = new ClientOwner();
        $client = new Client();
        $client->setGrants(['test_grant']);

        $this->validator->validate($client, $constraint);

        $this->assertNoViolation();
    }

    public function testOnClientGrantsAndEmptyUserClassAndId()
    {
        $constraint = new ClientOwner();
        $client = new Client();
        $client->setGrants(['client_credentials']);

        $this->validator->validate($client, $constraint);

        $this->buildViolation($constraint->message)
            ->atPath('property.path.owner')
            ->assertRaised();
    }
}
