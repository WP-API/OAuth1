# The Authorization Flow

The key to understanding how OAuth works is understanding the authorization flow. This is the process clients go through to link to a site.

The flow with the OAuth plugin is called the **three-legged flow**, thanks to the three primary steps involved:

* **Temporary Credentials Acquisition**: The client gets a set of temporary credentials from the server.
* **Authorization**: The user "authorizes" the request token to access their account.
* **Token Exchange**: The client exchanges the short-lived temporary credentials for a long-lived token.

## Temporary Credentials Acquisition

The first step to authorization is acquiring temporary credentials (also known as a **Request Token**). These credentials are short-lived (typically 24 hours), and are used purely for the initial authorization process. They don't grant any access to data on the server, and cannot be used for anything except the authorization flow.

These credentials are acquired by an initial HTTP request to the server. The client starts by sending a POST request to the temporary credential URL, typically `/oauth1/request` with the plugin. (This URL should be autodiscovered from the API, as individual sites may move this route, or delegate the process to another server.) This looks something like:

This request includes the client key (`oauth_consumer_key`), the authorization callback (`oauth_callback`), and the request signature (`oauth_signature` and `oauth_signature_method`). This looks something like:

```
POST /oauth1/request HTTP/1.1
Host: server.example.com
Authorization: OAuth realm="Example",
               oauth_consumer_key="jd83jd92dhsh93js",
               oauth_signature_method="HMAC-SHA1",
               oauth_timestamp="123456789",
               oauth_nonce="7d8f3e4a",
               oauth_callback="http%3A%2F%2Fclient.example.com%2Fcb",
               oauth_signature="..."
```

The server checks the key and signature to ensure the client  is valid. It also checks the callback to ensure it's valid for the client.

Once the checks are complete, the server creates a new set of Temporary Credentials (`oauth_token` and `oauth_token_secret`) and returns them in the HTTP response (URL encoded). This looks something like:

```
HTTP/1.1 200 OK
Content-Type: application/x-www-form-urlencoded

oauth_token=hdk48Djdsa&oauth_token_secret=xyz4992k83j47x0b&oauth_callback_confirmed=true
```

These credentials are then used as the `oauth_token` and `oauth_token_secret` parameters for the Authorization and Token Exchange steps.

(The `oauth_callback_confirmed=true` will always be returned, and indicates that the protocol is OAuth 1.0a.)


## Authorization

The next step in the flow is the authorization process. This is a user-facing step, and the one that most users will be familiar with.

Using the authorization URL supplied by the site (typically `/oauth1/authorize`), the client appends the temporary credential key (`oauth_token` from above) to the URL as a query parameter (again as `oauth_token`). The client then directs the user to this URL. Typically, this is done via a redirect for in-browser clients, or opening a browser for native clients.

The user then logs in if they aren't already, and authorizes the client. They can also choose to cancel the authorization process if they don't want to link the client.

If the user authorizes the client, the site then marks the token as authorized, and redirects the user back to the callback URL. The callback URL includes two extra query parameters: `oauth_token` (the same temporary credential token) and `oauth_verifier`, a CSRF token that needs to be passed in the next step.


## Token Exchange

The final step in authorization is to exchange the temporary credentials (request token) for long-lived credentials (also known as an **Access Token**). This request also destroys the temporary credentials.

The temporary credentials are converted to long-lived credentials by sending a POST request to the token request endpoint (typically `/oauth1/access`). This request must be signed by the temporary credentials, and must include the `oauth_verifier` token from the authorization step. The request looks something like:

```
POST /oauth1/access HTTP/1.1
Host: server.example.com
Authorization: OAuth realm="Example",
               oauth_consumer_key="jd83jd92dhsh93js",
               oauth_token="hdk48Djdsa",
               oauth_signature_method="HMAC-SHA1",
               oauth_timestamp="123456789",
               oauth_nonce="7d8f3e4a",
               oauth_verifier="473f82d3",
               oauth_signature="..."
```

The server again checks the key and signature, as well as also checking the verifier token to [avoid CSRF attacks](http://oauth.net/advisories/2009-1/).

Assuming these checks all pass, the server will respond with the final set of credentials in the HTTP response body (form data, URL-encoded):

```
HTTP/1.1 200 OK
Content-Type: application/x-www-form-urlencoded

oauth_token=j49ddk933skd9dks&oauth_token_secret=ll399dj47dskfjdk
```

At this point, you can now discard the temporary credentials (as they are now useless), as well as the verifier token.

Congratulations, your client is now linked to the site!