# Why OAuth?

When developing a REST API, there's no shortage of possible authentication options to connect to your site. These options span from simple username and password schemes up to much more complex systems. Why choose OAuth out of all of these?

OAuth is built around a singular core concept: **delegated authorization**. Unlike traditional username and password systems, or even API keys, OAuth doesn't have a single set of credentials. Instead, it splits the concept of credentials into two: client credentials, and user tokens. Clients register with sites they want to access, but this doesn't give them any inherent access. Users then authorize the client to perform actions on their behalf.

Decoupling these pieces gives better flexibility and security. If a client is compromised or accidentally leaks credentials, these can be revoked, disconnecting the client from all users. If a single user wants to disconnect the client, they can revoke the user token issued to the client. Combined, this gives both site owners and users control over their data.

This also crucially avoids the anti-pattern of giving credentials to external applications. In particular, OAuth itself provides **no ability to exchange a username and password for a user token**. This reinforces that users should never give their username and password to other applications. This also helps mitigate phishing exploits.