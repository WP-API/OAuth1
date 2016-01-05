# Signing Requests

One of the hardest parts of working with OAuth 1 is signing requests. It's important to understand the process from the start.

Even once you understand the process, it's recommended you use an existing library for this process. There are a lot of intricacies and edge cases to signing requests that are easy to miss. If you're ever in doubt on any details, the [OAuth RFC](https://tools.ietf.org/html/rfc5849#section-3.4) is the canonical reference on signatures; this is only an easier guide to it.

Request signing in OAuth is a key part of ensuring your application can't be spoofed. This uses a pre-established **shared secret** only known by the server and the client, which is a key reason why you should keep your credentials secret. This secret is then mixed with the request data and a nonce to ensure the signature can't be used multiple times.

**Note for experienced developers:** The OAuth plugin only supports HMAC-SHA1 signatures, and PHP-style GET parameters (`a[]=1&a[]=2`) are treated literally, with the `[]` included in the parameter names. **This may differ from other PHP-powered OAuth servers.**


## Base String

Before you can create a signature, you need something to sign. The first step is to take the request you're about to send and turn it into a single string. This needs to take into consideration the whole request, so it's generate it as late as possible. Ideally, using an OAuth implementation built into your HTTP client will ensure your base string is accurate.

The base string uses three pieces of data: the HTTP method (`GET`, `POST`, etc), the URL (without GET parameters), and any passed parameters. These follow [a very specific set of rules](https://tools.ietf.org/html/rfc5849#section-3.4.1), which loosely summarised are:

* **Method:** Uppercase HTTP method.
* **URL:** Lowercase scheme and host, port excluded if 80 for HTTP or 443 for SSL.
* **Request Parameters**: OAuth parameters from `Authorization` header (excluding `oauth_signature` itself), GET parameters from the URL, and POST parameters if they're form encoded (`a=b&c=d` format; **not** JSON). Encode the name and value for each, sort by name (and value for duplicate keys). Combine key and value with a `=`, then concatenate with `&` into a string.

These pieces are then combined by URL-encoding each, then concatenating with `&` into a single string.

For example, for the following request:
```
POST /wp-json/wp/v2/posts
Host: example.com
Authorization: OAuth
               oauth_consumer_key="key"
               oauth_token="token"
               oauth_signature_method="HMAC-SHA1"
               oauth_timestamp="123456789",
               oauth_nonce="nonce",
               oauth_signature="..."

{
    "title": "Hello World!"
}
```

The base string pieces are:
* Method: `POST`
* URL: `http://example.com/wp-json/wp/v2/posts`
* Params: `oauth_consumer_key=key&oauth_nonce=nonce&oauth_signature_method=HMAC-SHA1&oauth_timestamp=123456789&oauth_token=token`

The resulting base string would then be:
```
POST&http%3A%2F%2Fexample.com%2Fwp-json%2Fwp%2Fv2%2Fposts&oauth_consumer_key%3Dkey%26oauth_nonce%3Dnonce%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D123456789%26oauth_token%3Dtoken
```


## Signature Key

The OAuth plugin only supports a single signature method: HMAC-SHA1. This uses a HMAC (Hash-based Message Authentication Code), which looks similar to a normal SHA1 hash, but differs significantly. Importantly, it's immune to [length extension attacks](https://en.wikipedia.org/wiki/Length_extension_attack). It also needs two pieces: a **key** and the **text** to hash. The text is the base string created above.

The signature key for HMAC-SHA1 is created by taking the client/consumer secret and the token secret, URL-encoding each, then concatenating them with `&` into a string.

This process is always the same, **even if you don't have a token yet**.

For example, if your client secret is `abcd` and your token secret is `1234`, the key is `abcd&1234`. If your client secret is `abcd`, and you don't have a token yet, the key is `abcd&`.


## Signature

Once you have the base string and the signature, you can create the signature itself. The OAuth plugin only supports HMAC-SHA1 signatures, so the signature is always set to the result of `HMAC-SHA (key, text)`.

The HMAC key should be set to the signature key as above, and the HMAC text should be set to the base string. The result of the HMAC hashing is used as the signature.

(The hash should be the base64-encoded digest. Many languages handle this by default, but you may need to base64-encode it manually if not. This should always look like "wOJIO9A2W5mFwDgiDvZbTSMK/PY=", not raw binary data.)

Even if you're writing the signature handling from scratch, the HMAC hashing should **always be handled by an existing library**. HMAC-SHA1 is built into many languages natively, and libraries are available for basically every other language. Do not write your own code to handle hashing.

For example, in PHP, the `hash_hmac` function can be used to generate HMAC hashes:

```php
$base_string = 'POST&http...';
$key = 'abcd&1234';

$signature = hash_hmac( 'sha1', $base_string, $key );
```


## Common Problems

Signatures are without a doubt the hardest part of the OAuth 1 process. If your signature is incorrect, you'll receive a `json_oauth1_signature_mismatch`. Here's a couple of things that are easy to fix.


### Array Parameters

If you're generating your signature in PHP and you have array parameters (that is, `a[]=1&a[]=2`), you may be generating parameters incorrectly. Some PHP signature implementations incorrectly treat this as `a=1&a=2`, or may even generate `a=Array`. Check that your implementation correctly generates these.

### JSON Data

When sending data to the REST API, you'll likely be sending JSON data as the body. These parameters should **not** be included in the base string; the OAuth specification explicitly states that only form-encoded data should be included.
