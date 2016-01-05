# Registering an Application

Before you can talk to the server, you need to establish your credentials on the site. This involves registering your application with the site.

Applications can only be registered by site administrators, and must be registered on each site individually. (We're working on making it possible in the future to register once with a central authority to make this process easier.)

To register an application, open your site dashboard and head to Users > Applications, then click Add New. You'll need to enter the name of the application, an optional description, and the callback URL. This callback URL is used during the authorization process to redirect users back to after connecting. This URL can be changed later if you don't have a callback endpoint yet.

## Callback URLs

The callback URL is used during the authorization process. After users authorize your application on the site, they'll be redirected back to your callback URL. This callback needs to save the verifier token passed in, which is used in the third leg of the flow. The callback also typically starts the third leg (token exchange) on the server side. (The next section, [the Authorization Flow](Auth-Flow.md), expands more on how the callback URL is used.)

At the start of the OAuth flow, you pass in your OAuth callback URL for the specific request, which allows you to customise the callback URL for each request as needed. The OAuth plugin requires that your supplied callback URL match the scheme, authority (user and password part), host, port, and path of the registered callback URL. Only the query parameters and fragment (hash part) may differ from your registered URL. (This differs from some OAuth implementations, which allow subpaths of the callback URL.)

For sites with multiple domains or subdomains (e.g. a WordPress multisite network), the recommended method for handling this is to have a singular "main" callback URL which redirects to the specific site. During the request process, the site ID can then be added to the callback URL as a query parameter.

[Non-web applications](../advanced/Desktop.md) may wish to use custom URL schemes, or out-of-band handling. Out-of-band handling is triggered by setting the callback URL to the string `oob`. Rather than redirect after authorization, the site will instead display the verifier code to the user, which they then copy-and-paste or otherwise provide to the application.
