# Web Clients

OAuth was originally designed for working with web clients, so the process should be fairly smooth for most developers. There are some use cases that are tricky however, although not impossible to work with.


## Distributed or Multi-Domain Clients

One issue for clients with multiple domains (such as multisite WordPress installs) is callbacks: clients can only have a single registered callback, with no variation in most of the URL. This can make working with multiple domains tricky.

This can be easily handled by adding extra query parameters to the callback URL, as these **can** be set on a per-request basis. These can then be handled by your callback URL to pass on to a secondary callback.

For example, for a WordPress multisite, set the callback URL to a URL on the main site. A `site={id}` parameter can then be added when setting the callback for the request. The callback can then redirect the user's browser to a per-site callback based on this parameter (ensuring to pass along the `oauth_token` and `oauth_verifier` parameters.)

**Note:** When using this method, be sure to verify the site. Check that the request token being handled was actually created by the site asking for it. If you're using domains instead of site IDs, be *very* careful not to redirect to an unknown domain. Failure to check this can easily lead to CSRF (phishing) attacks.


## In-Browser Clients

Increasingly with modern JavaScript-based applications, the application may run entirely in the user's browser. OAuth 1 was (unfortunately) not designed for this use case. OAuth 2 goes a long way to correcting this, but as mentioned previously, [we can't use it :(](../introduction/OAuth-1.md)

This primarily falls down to  the application secret. OAuth 1.0a relies on the client secret being secret (duh) as the basis for the authorization flow. This is core to the signature process. Without this being secret, other applications can issue their own tokens as your application. OAuth 2 makes allowances for clients with public secrets with the `implicit` flow.

The simplest way to handle this is to introduce a minimal server-side component. This can be created from scratch, or a prebuilt server such as [Guardian](http://guardianjs.com/) can be used instead.


## Best Practices

* Never expose secrets, such as in JS client-side applications. Instead, use a proxy to handle the OAuth authentication.
* Similarly, never expose the verifier to sites outside of your control. The verifier is specifically intended for CSRF mitigation, so be exceedingly careful before passing it on to another URL.