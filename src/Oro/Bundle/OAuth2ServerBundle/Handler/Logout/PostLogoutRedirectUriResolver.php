<?php

declare(strict_types=1);

namespace Oro\Bundle\OAuth2ServerBundle\Handler\Logout;

use League\OAuth2\Server\RedirectUriValidators\RedirectUriValidator;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Handler\Logout\Exception\InvalidLogoutRequestException;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * Accepts a logout request only for an active OAuth application of the requested type (storefront or back-office)
 * that uses the authorization code grant, allows logout and has the requested post-logout redirect URI registered.
 */
class PostLogoutRedirectUriResolver implements PostLogoutRedirectUriResolverInterface
{
    public const string CLIENT_ID_PARAMETER = 'client_id';
    public const string POST_LOGOUT_REDIRECT_URI_PARAMETER = 'post_logout_redirect_uri';

    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {
    }

    #[\Override]
    public function resolve(Request $request, bool $frontend): string
    {
        $client = $this->getClient($request->query->getString(self::CLIENT_ID_PARAMETER));
        if ($client->isFrontend() !== $frontend) {
            throw InvalidLogoutRequestException::clientTypeMismatch();
        }

        if (!$client->isLogoutAllowed()) {
            throw InvalidLogoutRequestException::logoutNotAllowed();
        }

        // logout settings are effective only for the authorization code flow sign-in
        if (!\in_array(Client::AUTHORIZATION_CODE, $client->getGrants(), true)) {
            throw InvalidLogoutRequestException::authorizationCodeGrantNotSupported();
        }

        $postLogoutRedirectUri = $request->query->getString(self::POST_LOGOUT_REDIRECT_URI_PARAMETER);
        if (
            '' === $postLogoutRedirectUri
            || !$this->isPostLogoutRedirectUriRegistered($client, $postLogoutRedirectUri)
        ) {
            throw InvalidLogoutRequestException::unregisteredPostLogoutRedirectUri();
        }

        return $postLogoutRedirectUri;
    }

    /**
     * Uses the same matching as the login redirect URIs: an exact match,
     * except the port of loopback URIs (RFC 8252), which is ignored.
     */
    private function isPostLogoutRedirectUriRegistered(ClientEntity $client, string $postLogoutRedirectUri): bool
    {
        return (new RedirectUriValidator($client->getPostLogoutRedirectUris()))
            ->validateRedirectUri($postLogoutRedirectUri);
    }

    private function getClient(string $clientId): ClientEntity
    {
        if ('' === $clientId) {
            throw InvalidLogoutRequestException::unknownClient();
        }

        $client = $this->clientRepository->getClientEntity($clientId);
        if (!$client instanceof ClientEntity) {
            throw InvalidLogoutRequestException::unknownClient();
        }

        return $client;
    }
}
