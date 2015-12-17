# [WP REST API - OAuth 1.0a Server](http://oauth1.wp-api.org/)

Connect applications to your WordPress site without ever giving away your password.

This plugin uses the OAuth 1.0a protocol to allow delegated authorization; that is, to allow applications to access a site using a set of secondary credentials. This allows server administrators to control which applications can access the site, as well as allowing users to control which applications have access to their data.

This plugin only supports WordPress >= 4.4.

## New to OAuth

We strongly recommend you use an existing OAuth library. You'll be best off if you understand the authorization process, but leave the actual implementation to well-tested libraries, as there are a lot of edge cases.

Start reading from [the Introduction](docs/introduction/README.md) to get started!

## For OAuth Veterans

If you already know how to use OAuth, here's the lowdown:

* The plugin uses **OAuth 1.0a** in
* We use the **three-legged flow**
* To find the REST API index, apply the [API autodiscovery process](http://v2.wp-api.org/guide/discovery/)
* The endpoints for the OAuth process are available in the REST API index: check for `$.authentication.oauth1` in the index data.
    * The **temporary credentials** (request token) endpoint is `$.authentication.oauth1.request` (typically `/oauth1/request`)
    * The **authorization** endpoint is `$.authentication.oauth1.authorize` (typically `/oauth1/authorize`)
    * The **token exchange** (access token) endpoint is `$.authentication.oauth1.access` (typically `/oauth1/access`)
* Your callback URL must match the registered callback URL for the application in the scheme, authority (user/password) host, port, and path sections. (**Subpaths are not allowed.**)
* The only signature method supported is **HMAC-SHA1**.
* OAuth parameters are supported in the Authorization header, query (GET) parameters, or request body (POST) parameters (if encoded as `application/x-www-form-urlencoded`). **OAuth parameters are not supported in JSON data.**
