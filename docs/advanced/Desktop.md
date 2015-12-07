# Desktop/Mobile Clients

OAuth was originally designed for web applications, so desktop and mobile clients may wish to use the OAuth flow slightly differently. The authorization flow for OAuth requires both a browser and a callback URL for the second leg.


## Callback URL Schemes

The OAuth plugin supports any valid URL scheme, including custom schemes. This allows using custom schemes for callback URLs, which can then trigger your application. Note that for custom schemes, the authority (user and password) part must be empty, and the host **must not be empty** and must not contain invalid characters (such as `:#?[]`).

For example, the following URLs are **invalid**:
* `custom-app://`
* `custom-app://?oauth_callback`
* `custom-app://user:pass@oauth_callback`

The following URLs are **valid**:
* `custom-app://oauth_callback`
* `custom-app://oauth?callback`
* `custom-app://oauth/callback`
* `custom-app://oauth_callback:42`


## Out-of-Band Flow

For clients without the ability to handle a callback URL, an out-of-band flow can be used. Rather than redirecting the user after authorization, this flow displays the verifier token to the user to copy to the client.

To trigger the out-of-band flow, the callback URL must be set to `oob`. After the user has authorized the application, they'll be redirected to an internal page on the site which displays the verifier token. This can either be copy-and-pasted into the client (e.g. for command-line applications), or typed in manually.

With the callback URL set to `oob`, the supplied callback and registered callback must match exactly, and must be `oob`. This means that clients can either have a callback URL **or** out-of-band handling, and cannot work with both.


## Best Practices

* Clients should use callback URLs if at all possible, with out-of-band flow as a last resort.
* Clients should prefer the system browser rather a built-in browser, as the former typically has allows better usage of saved passwords. Using a built-in browser also gives a dangerous signal to users, as a compromised app could fake a login screen and phish their credentials.
