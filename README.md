# OAuth 2.0 Client

This package makes it simple to integrate your application with freelancer OAuth 2.0 service providers.

## Requirements

The following versions of PHP are supported.


* PHP 8.0
* HHVM

## Usage

### Initialize Freelancer OAuth2 Provider

It is recommended to have an understanding of oauth before using this client. For an introduction to Oauth2, see
https://www.digitalocean.com/community/tutorials/an-introduction-to-oauth-2, or email and ask the Freelancer Identity Team (identity@freelancer.com).

Note: Create your application from http://accounts.freelancer.com/settings/create_app
and get it approved before you start

``` php
require __DIR__ . '/vendor/autoload.php';
use Sergphp\OAuth2\Client\Provider\FreelancerIdentity;
use Sergphp\OAuth2\Client\Provider\FreelancerIdentityException;

$provider = new FreelancerIdentity([
    'clientId' => '<your-client-id>',
    'clientSecret' => '<your-client-secret>',
    'redirectUri' => '<your-client-redirect-uri>',
    'scopes' => [<scopes-array>], // Optional only needed when retrieve access token
    'prompt' => [<prompt-step-array>], // Optional only needed when retrieve access token
    'advancedScopes' => [<advanced-scopes-array>], // Optional only needed when retrieve access token
    'sandbox' => true, // to play with https://accounts.freelancer-sandbox.com
]);
```

### Authorization Code Grant

The authorization code grant type is the most common grant type used when authenticating users with freelancer service. This grant type utilizes a client (this library), a server (the service provider), and a resource owner (the user with credentials to a protected or owned resource) to request access to resources owned by the user. This is often referred to as 3-legged OAuth, since there are three parties involved.

``` php

// Check given error
if (isset($_GET['error'])) {
    exit($_GET['error']);
} elseif (!isset($_GET['code'])) {
    // If we don't have an authorization code then get one
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    $authorizationUrl = $provider->getAuthorizationUrl();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;
} else {
    try {
        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Store this bearer token in your data store for future use
        // including these information
        // token_type, expires_in, scope, access_token and refresh_token
        storeAccessTokenInYourDataStore($provider->accessTokenArray);

        // We have an access token, which we may use in authenticated
        // requests against the freelancer identity and freelancer API.
        echo $accessToken->getToken() . "\n";
        echo $accessToken->getRefreshToken() . "\n";
        echo $accessToken->getExpires() . "\n";
        echo ($accessToken->hasExpired() ? 'expired' : 'not expired') . "\n";

        // Using the access token, we may look up details about the
        // resource owner.
        $resourceOwner = $provider->getResourceOwner($accessToken);
        var_export($resourceOwner);
    } catch (FreelancerIdentityException $e) {
        // Failed to get the access token or user details.
        exit($e->getMessage());
    }
}
```

### Making an authorized request to freelancer API service.

In order to user freelancer API from you applications, your application needs to be
created with certain advanced scopes then you can request user to grant these
advanced scopes when retrieve access token.

Take POST https://www.freelancer.com/api/projects/0.1/projects/
Doc reference: https://www.freelancer.com/api/docs/#create-new-project
Your application needs to have 'fln:project:create' advanced scopes on creation, and your
user will need to consent on that scope so the granted access token now have the permission
to call this endpoint.


``` php

$provider = new FreelancerIdentity();
try {
    $tokenArray = getAccessTokenFromYourDataStore();
    $provider->setAccessTokenFromArray($tokenArray);

    if (!$provider->accessToken->hasExpired()) {

        $request = $provider->getAuthenticatedRequest(
            'POST',
            $provider->apiBaseUri.'/projects/0.1/projects/',
            [
                "headers" => ["Content-Type" => "application/json"],
                "body" => '{
                    "title": "Build my Super Website!",
                    "description": "I need this website to make visual basic GUIs",
                    "currency": { "id": 1 },
                    "budget": { "minimum": 300 },
                    "jobs": [ { "id": 7 }, { "id": 3 } ]
                }'
            ]
        );
        $response = $provider->getResponse($request);
        var_export($response);
    } else {
        // refresh your token
    }
} catch (FreelancerIdentityException $e) {

    // Failed to get response
    exit($e->getMessage());
}
```

### Refreshing a Token

Once your application is authorized, you can refresh an expired token using a refresh token rather than going through the entire process of obtaining a brand new token. To do so, simply reuse this refresh token from your data store to request a refresh.

``` php
$provider = new FreelancerIdentity([
    'clientId' => '<your-client-id>',
]);
$existingAccessTokenArray = getAccessTokenFromYourDataStore();
$provider->setAccessTokenFromArray($existingAccessTokenArray);

try {
    if ($provider->accessToken->hasExpired()) {
        $newAccessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $provider->accessToken->getRefreshToken()
        ]);

        // Purge old access token and store new access token to your data store.
    }
} catch (FreelancerIdentityException $e) {
    // Failed to refresh token
    exit($e->getMessage());
}

```


### Client Credentials Grant

When your application is acting on its own behalf to access resources it controls/owns in a service provider, it may use the client credentials grant type. This is best used when the credentials for your application are stored privately and never exposed (e.g. through the web browser, etc.) to end-users. This grant type functions similarly to the resource owner password credentials grant type, but it does not request a user's username or password. It uses only the client ID and secret issued to your client by the service provider.

``` php

try {

    // Try to get an access token using the client credentials grant.
    $accessToken = $provider->getAccessToken('client_credentials');

    // Store this bearer token in your data store for future use
    // including these information
    // token_type, expires_in, scope and access_token
    storeAccessTokenInYourDataStore($provider->accessTokenArray);

} catch (FreelancerIdentityException $e) {

    // Failed to get the access token
    exit($e->getMessage());

}
```


## Install

Via Composer

``` bash
$ composer require serg-php/freelancer-oauth2-client -o
```

## License

The MIT License (MIT). Please see [License File](https://github.com/serg-php/freelancer-oauth2-client/blob/master/LICENSE) for more information.