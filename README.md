wp-linotp-auth
==============

Two factor authentication for wordpress to authenticate against LinOTP.


Installation
============

For installing the wordpress plugin copy the file wp-linotp-auth.php to the wordpress directory

  ./wp-content/plugins

Configuration
=============

Please add at least a Hostname or IP address of your LinOTP server.
If you do not want to verify the SSL certificate of the LinOTP server, enter "0" at the verification configuration.

Please note: You need to have the same users in the LinOTP server. 
You can achieve this by configuring an SQL Resolver!

Troubleshooting
===============

If you misconfigured the plugin, you can not access your wordpress anymore.

You could either:

 adapt the entry in the table  wp_options where option_name="active_plugins";

or you can:

  remove the function wp_authenticate from wp-linotp-auth.php 

or you can uncomment this line:

  // return new WP_User($username);
 
But be aware: This gives access to any user with any password!
