The upgrade instructions are available at [Oro documentation website](https://doc.oroinc.com/master/backend/setup/upgrade-to-new-version/).

The current file describes significant changes in the code that may affect the upgrade of your customizations.

## UNRELEASED

### Added

#### OAuth2ServerBundle
* Added command `oro:oauth-server:generate-keys` that generates RSA keys for using in OAuth2 server.
* Added cron command `oro:cron:oauth-server:check-keys-permissions` that checks if RSA private key file has secure permissions and creates a notification alert if not.
* Added `\Oro\Bundle\OAuth2ServerBundle\Client\AccessTokenClient` that makes internal requests to provide an OAuth2 access token.
* Added `\Oro\Bundle\OAuth2ServerBundle\Client\AuthorizationCodeClient` that makes internal requests to provide an OAuth2 authorization code.
* Added the ability to fetch an access token from a cookie instead of `Authorizarion` header in the `oauth2` authentication method - via the `authorization_cookies` setting.
* Documented the storefront visitor authorization endpoint (`GET /oauth2-token/authorize-visitor`) in the frontend OpenAPI specification.
* Added the storefront logout endpoint `GET /oauth2-token/logout?client_id=...&post_logout_redirect_uri=...` and the back-office logout endpoint `GET {backend_prefix}/oauth2-token/logout?client_id=...&post_logout_redirect_uri=...`. Each endpoint ends the session that the authorization code flow sign-in opened and redirects back to the OAuth application.
    * Added `\Oro\Bundle\OAuth2ServerBundle\Controller\LogoutController` (routes `oro_oauth2_server_frontend_logout` and `oro_oauth2_server_logout`).
    * Added the `logoutAllowed` and `postLogoutRedirectUris` fields to `\Oro\Bundle\OAuth2ServerBundle\Entity\Client` ("Enable Logout" and "Post-Logout URLs" on the OAuth application form). Logout is disabled by default.
    * Added the `end_session_endpoint` to the storefront and back-office OAuth authorization server metadata.

## 6.0.0 (2024-03-30)

### Changed

#### OAuth2ServerBundle
* Updated `customer_user_oauth_application_created` email templates to extend it from `base_storefront` email template.
