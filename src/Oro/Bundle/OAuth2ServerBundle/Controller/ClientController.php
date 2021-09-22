<?php

namespace Oro\Bundle\OAuth2ServerBundle\Controller;

use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\FormBundle\Model\UpdateHandlerFacade;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\ClientType;
use Oro\Bundle\OAuth2ServerBundle\Form\Type\SystemClientType;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\OAuth2ServerBundle\Security\EncryptionKeysExistenceChecker;
use Oro\Bundle\SecurityBundle\Annotation\CsrfProtection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This controller covers widget-related functionality for OAuth 2.0 Client entity.
 */
class ClientController extends AbstractController
{
    private const SUPPORTED_CLIENT_TYPES = [
        'frontend'   => ['isFrontend' => true],
        'backoffice' => ['isFrontend' => false]
    ];

    private const CLIENT_CREDENTIALS_GRANT_TYPES = ['client_credentials'];

    /** @var array */
    private $supportedGrantTypes;

    /** @var ApiFeatureChecker */
    private $featureChecker;

    /**
     * @param string[]          $supportedGrantTypes
     * @param ApiFeatureChecker $featureChecker
     */
    public function __construct(array $supportedGrantTypes, ApiFeatureChecker $featureChecker)
    {
        $this->supportedGrantTypes = $supportedGrantTypes;
        $this->featureChecker = $featureChecker;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(parent::getSubscribedServices(), [
            EntityRoutingHelper::class,
            UpdateHandlerFacade::class,
            ClientManager::class,
            FormFactoryInterface::class,
            TranslatorInterface::class,
            EncryptionKeysExistenceChecker::class
        ]);
    }

    /**
     * @Route(
     *     "/",
     *     name="oro_oauth2_index",
     *     defaults={"type": "backoffice"}
     * )
     * @Route(
     *     "/frontend",
     *     name="oro_oauth2_frontend_index",
     *     defaults={"type": "frontend"}
     * )
     * @Template("@OroOAuth2Server/Client/index.html.twig")
     */
    public function indexAction(string $type): array
    {
        $this->checkTypeEnabled($type);

        if (!$this->getClientManager()->isViewGranted()) {
            throw $this->createAccessDeniedException();
        }

        return [
            'isFrontend'          => self::SUPPORTED_CLIENT_TYPES[$type]['isFrontend'],
            'encryptionKeysExist' => $this->isEncryptionKeysExist()
        ];
    }

    /**
     * @Route(
     *     "/{id}",
     *     name="oro_oauth2_view",
     *     requirements={"id"="\d+"},
     *     defaults={"type": "backoffice"}
     * )
     * @Route(
     *     "/frontend/{id}",
     *     name="oro_oauth2_frontend_view",
     *     requirements={"id"="\d+"},
     *     defaults={"type": "frontend"}
     * )
     *
     * @Template("@OroOAuth2Server/Client/view.html.twig")
     *
     * @param Client $entity
     * @param string $type
     *
     * @return mixed
     */
    public function viewAction(Client $entity, string $type): array
    {
        $this->checkTypeEnabled($type);
        $this->checkClientApplicableForType($entity, $type);

        if (!$this->getClientManager()->isViewGranted($entity)) {
            throw $this->createAccessDeniedException();
        }

        $user = null;
        if ($entity->getOwnerEntityId()) {
            $user = $this->getDoctrine()->getRepository($entity->getOwnerEntityClass())->find(
                $entity->getOwnerEntityId()
            );
        }

        return ['entity' => $entity, 'user' => $user, 'encryptionKeysExist' => $this->isEncryptionKeysExist()];
    }

    /**
     * @Route(
     *     "/create",
     *     name="oro_oauth2_create",
     *     defaults={"type": "backoffice"},
     *     methods={"GET", "POST"}
     * )
     * @Route(
     *     "/create/frontend",
     *     name="oro_oauth2_frontend_create",
     *     defaults={"type": "frontend"},
     *     methods={"GET", "POST"}
     * )
     *
     * @Template("@OroOAuth2Server/Client/create.html.twig")
     *
     * @param Request $request
     * @param string  $type
     *
     * @return mixed
     */
    public function createAction(Request $request, string $type)
    {
        $this->checkTypeEnabled($type);
        if (!$this->getClientManager()->isCreationGranted()) {
            throw $this->createAccessDeniedException();
        }

        $entity = new Client();
        $entity->setFrontend(self::SUPPORTED_CLIENT_TYPES[$type]['isFrontend']);

        $response = $this->update(
            $request,
            $entity,
            true,
            $this->supportedGrantTypes,
            $this->getTranslator()->trans('oro.oauth2server.client.created_message')
        );

        // change the Redirect response to custom template render to be able to show client secret information.
        if ($response instanceof RedirectResponse) {
            $response = $this->render(
                '@OroOAuth2Server/Client/createResult.html.twig',
                [
                    'entity' => $entity,
                    'type'   => $type
                ]
            );
        }

        return $response;
    }

    /**
     * @Route(
     *     "/update/{id}",
     *     name="oro_oauth2_update",
     *     defaults={"type": "backoffice"},
     *     methods={"GET", "POST"},
     *     requirements={"id"="\d+"}
     * )
     * @Route(
     *     "/update/frontend/{id}",
     *     name="oro_oauth2_frontend_update",
     *     defaults={"type": "frontend"},
     *     methods={"GET", "POST"},
     *     requirements={"id"="\d+"}
     * )
     *
     * @Template("@OroOAuth2Server/Client/update.html.twig")
     *
     * @param Request $request
     * @param Client  $entity
     * @param string  $type
     *
     * @return mixed
     */
    public function updateAction(Request $request, Client $entity, string $type)
    {
        $this->checkTypeEnabled($type);
        $this->checkClientApplicableForType($entity, $type);
        $this->checkModificationAccess($entity);

        return $this->update(
            $request,
            $entity,
            true,
            $this->supportedGrantTypes,
            $this->getTranslator()->trans('oro.oauth2server.client.updated_message')
        );
    }

    /**
     * @Route(
     *     "/create/client",
     *     name="oro_oauth2_server_client_create",
     *     methods={"GET", "POST"}
     * )
     * @Template("@OroOAuth2Server/Client/create.html.twig")
     *
     * @param Request $request
     *
     * @return array
     */
    public function createClientAction(Request $request)
    {
        if (!$this->getClientManager()->isCreationGranted()) {
            throw $this->createAccessDeniedException();
        }

        $entity = new Client();
        $entityRoutingHelper = $this->get(EntityRoutingHelper::class);
        $ownerEntityClass = $entityRoutingHelper->getEntityClassName($request);
        $ownerEntityId = (int)$entityRoutingHelper->getEntityId($request);
        if ($ownerEntityClass && $ownerEntityId) {
            if (!$this->featureChecker->isEnabledByClientOwnerClass($ownerEntityClass)) {
                throw $this->createNotFoundException();
            }
            $entity->setOwnerEntity($ownerEntityClass, $ownerEntityId);
        }

        return $this->update(
            $request,
            $entity,
            false,
            self::CLIENT_CREDENTIALS_GRANT_TYPES,
            null,
            $ownerEntityClass,
            $ownerEntityId
        );
    }

    /**
     * @Route(
     *     "/update/client/{id}",
     *     name="oro_oauth2_server_client_update",
     *     methods={"GET", "POST"},
     *     requirements={"id"="\d+"}
     * )
     * @Template("@OroOAuth2Server/Client/update.html.twig")
     *
     * @param Request $request
     * @param Client  $entity
     *
     * @return array
     */
    public function updateClientAction(Request $request, Client $entity)
    {
        $this->checkClientEnabled($entity);
        $this->checkModificationAccess($entity);

        return $this->update(
            $request,
            $entity,
            false,
            self::CLIENT_CREDENTIALS_GRANT_TYPES
        );
    }

    /**
     * @Route(
     *     "/delete/{id}",
     *     name="oro_oauth2_server_client_delete",
     *     methods={"DELETE"},
     *     requirements={"id"="\d+"}
     * )
     * @CsrfProtection()
     */
    public function deleteAction(Client $entity): Response
    {
        $this->checkClientEnabled($entity);
        if (!$this->getClientManager()->isDeletionGranted($entity)) {
            throw $this->createAccessDeniedException();
        }

        $this->getClientManager()->deleteClient($entity);

        return new JsonResponse(['successful' => true]);
    }

    /**
     * @Route(
     *     "/activate/{id}",
     *     name="oro_oauth2_server_client_activate",
     *     methods={"POST"},
     *     requirements={"id"="\d+"}
     * )
     * @CsrfProtection()
     */
    public function activateAction(Client $entity): Response
    {
        $this->checkClientEnabled($entity);
        $this->checkModificationAccess($entity);

        $this->getClientManager()->activateClient($entity);

        return new JsonResponse(['successful' => true]);
    }

    /**
     * @Route(
     *     "/deactivate/{id}",
     *     name="oro_oauth2_server_client_deactivate",
     *     methods={"POST"},
     *     requirements={"id"="\d+"}
     * )
     * @CsrfProtection()
     */
    public function deactivateAction(Client $entity): Response
    {
        $this->checkClientEnabled($entity);
        $this->checkModificationAccess($entity);

        $this->getClientManager()->deactivateClient($entity);

        return new JsonResponse(['successful' => true]);
    }

    /**
     * @param Request  $request
     * @param Client   $entity
     * @param bool     $isSystemOAuthApplication
     * @param string[] $grantTypes
     * @param string   $message
     * @param string   $ownerEntityClass
     * @param int      $ownerEntityId
     *
     * @return array
     */
    private function update(
        Request $request,
        Client $entity,
        $isSystemOAuthApplication,
        $grantTypes,
        $message = null,
        $ownerEntityClass = null,
        $ownerEntityId = null
    ) {
        return $this->get(UpdateHandlerFacade::class)->update(
            $entity,
            $this->getForm($entity, $isSystemOAuthApplication, $grantTypes),
            $message,
            $request,
            null,
            $this->getFormTemplateDataProvider($isSystemOAuthApplication, $ownerEntityClass, $ownerEntityId)
        );
    }

    /**
     * @param Client   $entity
     * @param bool     $isSystemOAuthApplication
     * @param string[] $grantTypes
     *
     * @return FormInterface
     */
    private function getForm(Client $entity, $isSystemOAuthApplication, $grantTypes): FormInterface
    {
        return $this->get(FormFactoryInterface::class)
            ->createNamed(
                'oro_oauth2_client',
                $isSystemOAuthApplication ? SystemClientType::class : ClientType::class,
                $entity,
                [
                    'grant_types' => $grantTypes,
                    'show_grants' => $isSystemOAuthApplication
                ]
            );
    }

    /**
     * @param bool   $isSystemApp
     * @param string $ownerClass
     * @param int    $ownerId
     *
     * @return callable
     */
    private function getFormTemplateDataProvider(
        bool $isSystemApp = false,
        string $ownerClass = null,
        int $ownerId = null
    ): callable {
        return function (Client $entity, FormInterface $form) use ($isSystemApp, $ownerClass, $ownerId) {
            if ($isSystemApp) {
                if (null === $entity->getId()) {
                    $formAction = $this->generateUrl(
                        $entity->isFrontend() ? 'oro_oauth2_frontend_create' : 'oro_oauth2_create'
                    );
                } else {
                    $formAction = $this->generateUrl(
                        $entity->isFrontend() ? 'oro_oauth2_frontend_update' : 'oro_oauth2_update',
                        ['id' => $entity->getId()]
                    );
                }
            } else {
                $parameters = [];
                if (null !== $ownerClass && null !== $ownerId) {
                    $parameters['entityClass'] = $ownerClass;
                    $parameters['entityId'] = $ownerId;
                }
                $formAction = null === $entity->getId()
                    ? $this->generateUrl('oro_oauth2_server_client_create', $parameters)
                    : $this->generateUrl(
                        'oro_oauth2_server_client_update',
                        array_merge($parameters, ['id' => $entity->getId()])
                    );
            }

            return [
                'entity'     => $entity,
                'form'       => $form->createView(),
                'formAction' => $formAction
            ];
        };
    }

    private function getClientManager(): ClientManager
    {
        return $this->get(ClientManager::class);
    }

    private function getTranslator(): TranslatorInterface
    {
        return $this->container->get(TranslatorInterface::class);
    }

    private function checkClientEnabled(Client $entity): void
    {
        if (!$this->featureChecker->isEnabledByClient($entity)) {
            throw $this->createNotFoundException();
        }
    }

    private function checkModificationAccess(Client $entity): void
    {
        if (!$this->getClientManager()->isModificationGranted($entity)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function checkTypeEnabled(string $type): void
    {
        if ($type === 'frontend') {
            if (!class_exists('Oro\Bundle\CustomerBundle\OroCustomerBundle')
                || !$this->featureChecker->isFrontendApiEnabled()
            ) {
                throw $this->createNotFoundException();
            }
        } elseif (!$this->featureChecker->isBackendApiEnabled()) {
            throw $this->createNotFoundException();
        }
    }

    private function checkClientApplicableForType(Client $client, string $type): void
    {
        $isFrontend = $type === 'frontend';
        if ($client->isFrontend() !== $isFrontend) {
            throw $this->createNotFoundException();
        }
    }

    private function isEncryptionKeysExist(): bool
    {
        $encryptionKeysExistenceChecker = $this->get(EncryptionKeysExistenceChecker::class);

        return
            $encryptionKeysExistenceChecker->isPrivateKeyExist()
            && $encryptionKeysExistenceChecker->isPublicKeyExist();
    }
}
