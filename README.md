# ClassicPress SSL Fix

This plugin provides a way to work around the issue "**cURL error 60: SSL
certificate problem: certificate has expired**" in ClassicPress and WordPress
in the most secure way possible, and also provides an admin page to determine
whether the issue exists on your site and recommend how to fix it.

This issue is occurring because one of the SSL certificates used by a large
portion of the Internet expired in September 2021. Its replacement is already
available, but many web servers (and other devices) are running an older
version of a key piece of software that doesn't know how to use the new
certificate properly.

## Plugin functions

If your site reports that it is using a recent enough version of OpenSSL to
perform external requests, then this plugin doesn't need to do anything.

Otherwise, this plugin will attempt to tell your ClassicPress/WordPress
installation not to use the expired certificate that causes this problem.  If
the expired certificate is not present in your web server's system certificate
bundle (part of the configuration set by your web host) then this is all that
is needed.

If the expired certificate is also present in your web server's system
certificate bundle then this will not work.  Your web server will need to be
upgraded (to a more recent version of PHP and/or the cURL extension for PHP) or
reconfigured (to remove the expired certificate from your system's certificate
store).  Until that is done, the only other option is to disable certificate
verification entirely for external requests. This is dangerous, so the plugin
provides a button to enable this mode for 3 minutes to allow you to complete
critical maintenance tasks like upgrades.

## Installation

Download the latest version of this plugin's code from GitHub using
[this link](https://github.com/ClassicPress-research/cp-ssl-fix/archive/refs/heads/master.zip)
and install it in your site's admin dashboard like any other plugin ("Plugins >
Add New" in the dashboard menu, then click the button to "Upload Plugin").

Activate the plugin, then go to "Tools > CP SSL Fix" in the dashboard menu.

## More technical details

This issue occurs when:

- your site is using the PHP cURL extension to make requests to external
  servers (this by itself is normal and correct)
- **and** the version of OpenSSL that is bundled with cURL is 1.0.2 or older
- **and** an expired certificate known as "DST Root CA X3" is present in your
  web server's system certificate bundle and/or the certificate bundle used by
  ClassicPress/WordPress. (cURL will always use the certificates in the system
  certificate bundle even though ClassicPress/WordPress specify their own
  certificate bundle, so the expired certificate needs to be removed from both
  places.)

When all of these conditions are met, your site will be unable to connect to
external servers that use SSL certificates issued by Let's Encrypt, which is a
large portion of the Internet, including api-v1.classicpress.net.

The best way to fix this issue is to get your web hosting provider to update
the software behind your PHP installation, and also remove the expired "DST
Root CA X3" certificate from your web server.

ClassicPress and WordPress will also be removing this certificate on their end,
but this is not always enough to fix the issue by itself.

In the meantime, this plugin can help get your site able to make requests to
external servers again.

## Links

- https://techcrunch.com/2021/09/21/lets-encrypt-root-expiry/
- https://forums.classicpress.net/t/sorry-we-cant-switch-this-site-to-classicpress-at-this-time/3654
- https://core.trac.wordpress.org/ticket/54207
- https://www.openssl.org/blog/blog/2021/09/13/LetsEncryptRootCertExpire/
