<?php

namespace Oro\Bundle\OAuth2ServerBundle\Form\Type;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CustomerBundle\Form\Type\CustomerUserSelectType;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Provider\ClientOwnerOrganizationsProvider;
use Oro\Bundle\UserBundle\Form\Type\OrganizationUserAclSelectType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * The form for OAuth 2.0 client with owner field.
 */
class SystemClientType extends AbstractClientType
{
    public const OWNER_FIELD = 'owner';

    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ClientOwnerOrganizationsProvider $organizationsProvider, ManagerRegistry $doctrine)
    {
        parent::__construct($organizationsProvider);
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $this->addOwnerEntityField($event);
        });

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $this->setOwnerValue($event);
            },
            100 // the listener should be executed before validation listener
        );
    }

    /**
     * Adds owner field to form.
     */
    private function addOwnerEntityField(FormEvent $event): void
    {
        /** @var Client $client */
        $client = $event->getData();
        if ($client->getId() && !\in_array('client_credentials', $client->getGrants(), true)) {
            return;
        }

        $event->getForm()->add(
            self::OWNER_FIELD,
            !$client->isFrontend() ? OrganizationUserAclSelectType::class : CustomerUserSelectType::class,
            [
                'required' => true,
                'mapped'   => false,
                'label'    => !$client->isFrontend()
                    ? 'oro.user.entity_label'
                    : 'oro.customer.customeruser.entity_label',
                'disabled' => null !== $client->getId()
            ]
        );

        $ownerId = $client->getOwnerEntityId();
        if ($ownerId) {
            $event->getForm()->get('owner')->setData(
                $this->doctrine->getRepository($client->getOwnerEntityClass())->find($ownerId)
            );
        }
    }

    /**
     * Sets the owner information to the client entity.
     */
    private function setOwnerValue(FormEvent $event): void
    {
        if (!$event->getForm()->has(self::OWNER_FIELD)) {
            return;
        }

        $owner = $event->getForm()->get(self::OWNER_FIELD)->getData();
        if (!$owner) {
            return;
        }

        /** @var Client $client */
        $client = $event->getData();
        $client->setOwnerEntity(ClassUtils::getClass($owner), $owner->getId());
    }
}
