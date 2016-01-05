# Why OAuth 1.0a?

If you've done research on OAuth, you might notice OAuth 2.0 exists. Why is the canonical authorization scheme using an older version of OAuth?

OAuth has a long and storied history behind it. The OAuth concept was born from Twitter (and others) needing delegated authorization for user accounts, primarily for API access. This then continued evolving with feedback from other parties (such as Google), before eventually being standardised as OAuth 1.0a in [RFC 5849](https://tools.ietf.org/html/rfc5849). OAuth was then further evolved and simplified in OAuth 2.0, standardised as [RFC 6749](https://tools.ietf.org/html/rfc6749).

The primary change from version 1 to 2 was the removal of the complicated signature system. This signature system was designed to ensure only the client can use the user tokens, since it relies on a shared secret. However, every request must be individually signed. Version 2 instead relies on SSL/TLS to handle message authenticity.

This means that **OAuth 2.0 requires HTTPS**. WordPress however does not. We need to be able to provide authentication for all sites, not just those with HTTPS.

With the impending changes to the HTTPS playing field with the Let's Encrypt certificate authority, we hope to be able to require SSL in the future and move to OAuth 2.0, but this is not yet feasible.

(Note: While the OAuth RFC requires SSL for some endpoints, [OAuth 1.0a](http://oauth.net/core/1.0a/) does not. This is a willful violation of the RFC, as we need to support non-SSL sites.)