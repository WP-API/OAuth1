# OAuth Authentication API
The following document describes the HTTP API for authenticating and authorizing
a remote client with a WordPress installation.

## Framework
The WordPress OAuth Authentication API ("OAuth API") is a HTTP-based API based
on the [OAuth 1.0a][RFC5849]. It also builds on the OAuth 1.0a specification
with custom parameters.

This document describes OAuth API version 0.1.

## Terminology
* "access token": A long-lived token used for accessing the site. Grants
  permissions to the client based on its scope.
* "client": A software program that accesses the OAuth API and provides services
  to a user.
* "request token": A short-lived token used during the OAuth process. Does not
  grant any permissions, and can only be used for the authorization steps.
* "site": A WordPress installation providing the OAuth API as a service
* "user": An end-user of an API client. Typically a registered user on the site.

Note that any relative URLs are taken as relative to the site's base URL.

## Motivation
The OAuth API is motivated by three main factors:

* The user should only ever enter their credentials into the site. Clients
  should not only be discouraged from asking for user credentials, but the site
  should also avoid providing a way to use them.

* The API must work on any site. The API must only use features available to the
  majority of sites in order to provide a useful utility.

* The API should be simple to implement in clients. Developers should be able to
  create clients by reusing existing libraries, rather than writing full
  custom solutions.

## Differences from OAuth 1.0a
The OAuth API extends OAuth 1.0a to provide additional functionality. The
following differences apply:

* The authorization endpoint ("Resource Owner authorization endpoint") MAY
  accept a `wp_scope` parameter, based on the OAuth 2.0 `scope` parameter.
  (See Step 2: Authorization)

## Step 0: Assessing Availability
Before beginning the authorization process, clients SHOULD assess whether the
site supports it. Due to the customizable nature of sites, this is not
guaranteed, as the OAuth API can be disabled or replaced.

To fulfill this requirement, the OAuth API interfaces with the WordPress JSON
REST API ("WP API"). Most clients using the OAuth API are expected to have the
ability to access the WP API.

The OAuth API exposes information on itself via the index endpoint of the WP
API, typically available at `/wp-json/`. The WP API is discoverable via the
RSD mechanism, and OAuth API clients using this data SHOULD use the RSD
mechanism, as described by the WP API documentation.

### Request
The client sends a HTTP GET request to the index endpoint of the WP API. The
location of the index endpoint is out of scope of this document, and is handled
by the WP API documentation.

### Response
The WP API index endpoint returns a JSON object of data relating to the site.
The OAuth API exposes data through the `authentication` value in the `oauth1`
property value.

The `oauth1` value ("API Description object") is a JSON object with the
following properties defined:

* `request` An absolute URL giving the location of the "Temporary Credential
  Request endpoint" (see Step 1: Request Tokens)
* `authorize`: An absolute URL giving the location of the "Resource Owner
  Authorization endpoint" (see Step 2: Authorization)
* `access`: An absolute URL giving the location of the "Token Request endpoint"
  (see Step 3: Access Tokens)
* `version`: A version string indicating the version of the OAuth API supported
  by the site. 

## Step 1: Request Tokens
The first step to the authorization process is to obtain a request token. This
step asks the site to issue a temporary token, used only for the authorization
process. This token is a short-expiry token which is not yet linked to a user.

This request follows the [Temporary Credentials][oauth-request] section of
[RFC5948][].

### Request
The client sends a HTTP POST request to `/oauth1/request` (the "Temporary
Credential Request endpoint"). This URL is also available via the API
Description object as the `request` property, and clients SHOULD use the URL
from the API Description object instead of hardcoding the URL.

This request should match the format as described in the OAuth 1.0a
specification, section 2.1.

This request can also contain the following parameters as an extension on top of
the OAuth 1.0a parameters:

* `wp_scope`: This is a space- or comma-separated field in the style of OAuth
  2.0's scope field. This represents a narrowing of the available permissions to
  the client. See Authorization Scope. This parameter is OPTIONAL, and defaults
  to "*" (all permissions).

### Response
The OAuth API returns a URL-encoded response of the OAuth request token data, as
described in the OAuth 1.0a specification, section 2.1.

## Step 2: Authorization
The second step to the authorization process is to request authorization from
the user. This step sends the user to the site, where the user then
authenticates and grants the requested permissions to the client. This
acceptance is stored with the request data on the site.

This request follows the [Resource Owner Authorization][oauth-authorize] section
of [RFC5849][], with additions.

### Request
The client sends the user to `/oauth1/authorize` (the "Resource Owner
Authorization endpoint"). This URL is also available via the API Description
object as the `authorize` property, and clients SHOULD use the URL from the API
Description object instead of hardcoding the URL.

This request should match the format as described in the OAuth 1.0a
specification, section 2.2.

This request can also contain the following parameters as an extension on top of
the OAuth 1.0a parameters:

* `wp_scope`: This is a space- or comma-separated field in the style of OAuth
  2.0's scope field. This represents a narrowing of the available permissions to
  the client. See Authorization Scope. This parameter is OPTIONAL, and defaults
  to either the `wp_scope` parameter as specified in the Request Token request,
  or "*" (all permissions) otherwise.

### Response
The site will redirect the user back to the `oauth_callback` as provided in the
Authorization step. The `oauth_token` and `oauth_verifier` parameters will be
appended to the callback URL as per the OAuth 1.0a standard.

In addition, a `wp_scope` parameter will be appended describing the actual scope
granted (see Authorization Scope).

## Step 3: Access Tokens
The third step to the authorization process is to use the now-authorized request
token to request an access token. This step asks the site to grant the client an
access token to use in future requests as authentication, using the request
token.

This request follows the [Token Credentials][oauth-access] section of
[RFC5849][].

### Request
The client sends the user to `/oauth1/access` (the "Token Request" endpoint).
This URL is also available via the API Description object as the "access"
property, and clients SHOULD use the URL from the API Description object instead
of hardcoding the URL.

This request should match the format as described in the OAuth 1.0a
specification, section 2.3.

### Response
The OAuth API returns a URL-encoded response of the OAuth access token data, as
described in the OAuth 1.0a specification, section 2.3.

## Authenticated Requests
...

## Authorization Scope
The OAuth API supports an additional parameter during both the Request Token
request and Authorization request. This `wp_scope` parameter is a list of
delimited strings of requested scopes. Scopes SHOULD be delimited by U+0020
SPACE characters, URL-encoded as `%20`. Clients MAY use U+0020 SPACE characters,
URL-encoded as `+`, or U+002C COMMA characters, URL-encoded as `%2c`.

The OAuth API will also return the `wp_scope` parameter to the callback URL
during the Authorization step, as a list of space-delimited strings of granted
scopes (U+0020 SPACE characters are encoded as `%20`). This response parameter
indicates the scope granted to the client for the token. This granted scope is
strictly equal to or less permissive than the requested scope; that is, clients
will never be granted additional permissions from those requested, but users may
restrict the client's scope further.

The default scope for clients that do not specify the `wp_scope` parameter is
`*`, indicating all permissions will be granted. This permission grants the
ability to perform any action the user has the capability to perform, including
any future capabilities they may be granted. This scope SHOULD be used
sparingly, as it presents a large attack surface.

### Available Scopes
The following scopes are available:

* `read`: Ability to read any public site data, or private data that the user
  has access to (such as privately published posts).

  Maps to:
  * `read`
  * `read_private_*` (requires Editor or above)

* `edit`: Ability to edit any public site data, or private data that the user
  has access to. Implies `read`.

  Requires Contributor or above.

  Maps to:
  * `edit_*`
  * `delete_*`
  * `upload_files` (requires Author or above)
  * `moderate_comments` (requires Editor or above)
  * `manage_categories` (requires Editor or above)
  * `edit_others_*` (requires Editor or above)
  * `edit_private_*` (requires Editor or above)
  * `edit_published_*` (requires Editor or above)
  * `delete_others_*` (requires Editor or above)
  * `delete_private_*` (requires Editor or above)
  * `delete_published_*` (requires Editor or above)

* `user.read`: Ability to read most user data, with the exception of the user's
  email address.

* `user.email`: Ability to read the user's email address. Use of the user's
  email address should conform to all local laws (for both the client and site)
  with regards to spam. Implies `user.read`.

* `user.edit`: Ability to edit any user data. Implies `user.read`
  and `user.email`.

* `admin.read`: Ability to read admin-only data.

  Requires Admin or Super Admin.

  Maps to:
  * `list_users`

* `admin.edit`: Ability to edit admin-only data.

  Requires Admin or Super Admin.

  Maps to:
  * `manage_options`
  * `install_plugins`
  * `update_plugins`
  * `install_themes`
  * `switch_themes`
  * `update_themes`
  * `edit_theme_options`
  * `update_core`
  * `edit_dashboard`

* `admin.users`: Ability to administrate users.

  Requires Admin or Super Admin. Implies `user.edit`.

  Maps to:
  * `list_users`
  * `create_users`
  * `edit_users`
  * `promote_users`
  * `remove_users`
  * `delete_users`

* `admin.import`: Ability to import data.

  Requires Admin or Super Admin. Implies `edit`.

  Maps to:
  * `import`

* `admin.export`: Ability to export data.

  Requires Admin or Super Admin. Implies `read`.

  Maps to:
  * `export`

For most applications, `read` and `user.read` are appropriate. For any
applications which need access to information about the current user,
`user.read` is recommended.

Any permissions requested that are not available to the current user will cause
an error to be returned to the client. Note that for permissions like `edit`,
users without the `upload_files` capability (e.g.) will **not** cause an error,
as the permission encompasses other capabilities. A user without the `edit_*`
capability **will** cause an error, however.

[RFC5849]: http://tools.ietf.org/html/rfc5849
[oauth-request]: http://tools.ietf.org/html/rfc5849#section-2.1
[oauth-authorize]: http://tools.ietf.org/html/rfc5849#section-2.2
[oauth-access]: http://tools.ietf.org/html/rfc5849#section-2.3
