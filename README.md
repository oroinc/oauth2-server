# OroOAuth2ServerBundle

OroOAuth2ServerBundle provides OAuth 2.0 authorization and resource server capabilities implemented
on top of [thephpleague/oauth2-server](https://github.com/thephpleague/oauth2-server) library.

Currently, Client Credentials is the only implemented authorization flow. See
[OAuth 2.0 Server Client Credentials Grant](https://oauth2.thephpleague.com/authorization-server/client-credentials-grant/)
and [OAuth 2.0 Client Credentials Grant](https://oauth.net/2/grant-types/client-credentials/) for details.

## Configuration

The default configuration of OroOAuth2ServerBundle is illustrated below:

``` yaml
oro_oauth2_server:
    authorization_server:

        # The lifetime in seconds of the access token.
        access_token_lifetime: 3600

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

The OAuth applications can be added and managed on the user view page.

## Usage

The authorization server entry point is `/oauth2-token`, it should be used to request the access token.

An example of a request for an access token:

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

The received access token can be used multiple times until it expires. A new token should be requested once
the previous token expires.

An example of an API request:

```
GET /api/users HTTP/1.1
Content-Type: application/vnd.api+json
Authorization: Bearer your access token
```
