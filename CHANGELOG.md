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
* Added the `oro_oauth2_server.session_transfer.return_url` configuration option - the URL of the application that started an OAuth2 Session Transfer. Storefront pages rendered in Session Transfer mode (see CheckoutBundle) link back to it. Defaults to `http://localhost:3000` for local development; set it to the storefront application's own URL in production.
* Documented the storefront visitor authorization endpoint (`GET /oauth2-token/authorize-visitor`) in the frontend OpenAPI specification.

## 6.0.0 (2024-03-30)

### Changed

#### OAuth2ServerBundle
* Updated `customer_user_oauth_application_created` email templates to extend it from `base_storefront` email template.
