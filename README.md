# OroOAuth2ServerBundle

OroOAuth2ServerBundle provides OAuth 2.0 authorization and resource server capabilities implemented
on top of [thephpleague/oauth2-server](https://github.com/thephpleague/oauth2-server) library.

Currently, Client Credentials and Password grants are implemented. 

See
[OAuth 2.0 Server Client Credentials Grant](https://oauth2.thephpleague.com/authorization-server/client-credentials-grant/)
and [OAuth 2.0 Client Credentials Grant](https://oauth.net/2/grant-types/client-credentials/) for details of Client Credentials grant.

See
[OAuth 2.0 Server Resource Owner Password Credentials Grant](https://oauth2.thephpleague.com/authorization-server/resource-owner-password-credentials-grant/)
and [OAuth 2.0 Password Grant](https://oauth.net/2/grant-types/password/) for details of Password grant.

## Configuration

The default configuration of OroOAuth2ServerBundle is illustrated below:

``` yaml
oro_oauth2_server:
    authorization_server:

        # The lifetime in seconds of the access token.
        access_token_lifetime: 3600 # 1 hour
        
        # The lifetime in seconds of the refresh token.
        refresh_token_lifetime: 18144000 # 30 days
        
        # Determines if refresh token grant is enabled.
        enable_refresh_token: true

        # The full path to the private key file that is used to sign JWT tokens.
        private_key: '%kernel.project_dir%/var/oauth_private.key'

        # The string that is used to encrypt refresh token and authorization token payload.
        # How to generate an encryption key: https://oauth2.thephpleague.com/installation/#string-password
        encryption_key: '%secret%'

        # The configuration of CORS requests
        cors:
            # The amount of seconds the user agent is allowed to cache CORS preflight requests
            preflight_max_age: 600

            # The list of origins that are allowed to send CORS requests
            allow_origins: [] # Example: ['https://foo.com', 'https://bar.com']

    resource_server:

        # The full path to the public key file that is used to verify JWT tokens.
        public_key: '%kernel.project_dir%/var/oauth_public.key'
```

In order to use OAuth 2.0 authorization, you need to generate private and public keys and place them
to locations specified in `authorization_server / private_key` and `resource_server / public_key` options.
See [Generating public and private keys](https://oauth2.thephpleague.com/installation/#generating-public-and-private-keys)
for details how to generate the keys.

## Manage OAuth Applications

Customer Credentials grant applications can be added and managed on the user view page.

All enabled grant applications can be added and managed on the OAuth Applications page.

## Create OAuth Application via Data Fixtures

The OAuth applications can be added using data fixtures. For example:

``` php
<?php

namespace Oro\Bundle\OAuth2ServerBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class LoadOAuthApplication extends AbstractFixture implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $client = new Client();
        $client->setOrganization($manager->getRepository(Organization::class)->getFirst());
        $client->setName('My Application');
        $client->setGrants(['password']);
        $client->setIdentifier('my_client_id');
        $client->setPlainSecret('my_client_secret');

        $this->container->get(ClientManager::class)->updateClient($client, false);

        $manager->persist($client);
        $manager->flush();
    }
}
```

To load data fixtures you can use either `oro:migration:data:load` or `oro:platform:update` commands.

## Usage

The authorization server entry point is `/oauth2-token`, it should be used to request the access token.

### An example of a request for an access token for Client credentials granted application:

Request

```
POST /oauth2-token HTTP/1.1
Content-Type: application/json
```

Request Body

``` json
{
    "grant_type": "client_credentials",
    "client_id": "your client identifier",
    "client_secret": "your client secret"
}
```

Response Body

``` json
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "your access token"
}
```
If the access token is received successfully, the date of getting the access token is updated
for the [application entity](src/Oro/Bundle/OAuth2ServerBundle/Entity/Client.php), and this data is displayed
in the applications datagrid.

The received access token can be used multiple times until it expires. A new token should be requested once
the previous token expires.

An example of an API request:

```
GET /api/users HTTP/1.1
Content-Type: application/vnd.api+json
Authorization: Bearer your access token
```

### An example of a request for an access token for Password granted application:

Request

```
POST /oauth2-token HTTP/1.1
Content-Type: application/json
```

Request Body

``` json
{
    "grant_type": "password",
    "client_id": "your client identifier",
    "client_secret": "your client secret",
    "username": "your user username",
    "password": "your user password"
}
```

Response Body

``` json
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "your access token",
    "refresh_token" "your refresh token"
}
```

The received access token can be used multiple times until it expires. 

An example of an API request:

```
GET /api/users HTTP/1.1
Content-Type: application/vnd.api+json
Authorization: Bearer your access token
```
### An example of a request for an access token if the assess token was expired.

When the access token expires for Password granted application, a new token should be requested with the refresh token:

Request

```
POST /oauth2-token HTTP/1.1
Content-Type: application/json
```

Request Body

``` json
{
    "grant_type": "refresh_token",
    "client_id": "your client identifier",
    "client_secret": "your client secret",
    "refresh_token": "your refresh token was returned with an access token"
}
```

Response Body

``` json
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "your new access token",
    "refresh_token" "your new refresh token"
}
```

If refresh token was expired, the request to the access token with Password grant type should be requested to get new 
access and refresh tokens.

## OAuth authorization in storefront API

If the system has the customer portal package installed, OAuth authorization for customer users to the storefront
API resources is enabled automatically.

Storefront applications can be managed from the application back-office under System > Storefront OAuth Applications.

Customer user email address should be used as a username to get access token for `password` grant applications. 

## Customer visitor authorization for storefront API

To be able to get the storefront API data without the customer user, you can authorize as a customer visitor
if the security firewall for storefront API resources is configured with `anonymous_customer_user`.

To get the access token for customer visitor, send `guest` as login and password:

Request

```
POST /oauth2-token HTTP/1.1
Content-Type: application/json
```

Request Body

``` json
{
    "grant_type": "password",
    "client_id": "your storefront client identifier",
    "client_secret": "your storefront client secret",
    "username": "guest",
    "password": "guest"
}
```

Response Body

``` json
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "your access token",
    "refresh_token" "your refresh token"
}
```

A new customer visitor is created after each such request.

To be able to work with the same customer visitor when the access token expires, use the refresh access token request.

Resources
---------

  * [OroCommerce, OroCRM and OroPlatform Documentation](https://doc.oroinc.com)
  * [Contributing](https://doc.oroinc.com/community/contribute/)
