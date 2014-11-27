# OAuth 1.0a Server for WordPress
This project is an OAuth 1.0a-compatible authentication method for WordPress.
This is a separate-but-related project to [WP API][], designed to provide
authentication suitable for the API.

## Documentation

Read the [plugin's documentation][docs].

[docs]: https://github.com/WP-API/OAuth1/tree/master/docs


## Quick Setup

Want to test out the OAuth API and work on it? Here's how you can set up your own
testing environment in a few easy steps:

1. Install [Vagrant](http://vagrantup.com/) and [VirtualBox](https://www.virtualbox.org/).
2. Clone [Chassis](https://github.com/Chassis/Chassis):

   ```bash
   git clone --recursive git@github.com:Chassis/Chassis.git api-tester
   ```

3. Grab a copy of WP API and OAuth API:

   ```bash
   cd api-tester
   mkdir -p content/plugins content/themes
   cp -r wp/wp-content/themes/* content/themes
   git clone git@github.com:WP-API/WP-API.git content/plugins/json-rest-api
   git clone git@github.com:WP-API/OAuth1.git content/plugins/oauth-server
   ```

4. Start the virtual machine:

   ```bash
   vagrant up
   ```

5. Browse to http://vagrant.local/wp/wp-admin/ and activate the WP API and OAuth
   API plugins

   ```
   Username: admin
   Password: password
   ```

6. Refer to the [documentation][docs] on how to connect an OAuth client.
