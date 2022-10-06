Mailbox Authentication Plugin for Joomla!
=========================================

This is a Joomla! plugin to authenticate users against one or more mail
servers via IMAP, NNTP, or POP3.  It forwards authentication credentials
provided by the user to one or more mail servers, then relays the
authentication decision of the mail servers back to Joomla!.

In simpler terms, it lets users log in to Joomla! with the same username and
password that they use for email, without the need to copy and synchronize the
accounts manually.

### Supported Features

 * Fully localizable
 * Set user email account based on configurable mail domain.
 * Authenticate to mail server using full email address.
 * Configable mail host address and port.
 * Optionally require secure (non-plaintext) authentication.
 * Support for TLS (optional or required) and SSL.
 * Optional validation of SSL certificate from mail server.
 * Authentication against multiple mail servers.
 * Supports the [Joomla! Update
   System](https://docs.joomla.org/Help36:Extensions_Extension_Manager_Update).


### Currently Unsupported Features

 * Does not integrate email/webmail into Joomla! or provide Single Sign-On
   (SSO) between email/webmail and Joomla!  Meaning users will still need to
   login to both Joomla! and email/webmail separately.


### Plugin Requirements

* **Joomla! 3.0 or later.**  (Confirmed working on Joomla! 4.0-alpha3.)
  There are previous versions of this plugin available [for Joomla!
  2.5](https://github.com/kevinoid/auth-mailbox-joomla/releases/tag/v1.0.9-for-joomla2.5)
  and [for Joomla!
  1.5](https://github.com/kevinoid/auth-mailbox-joomla/releases/tag/v1.0.9-for-joomla1.5).
* **[PHP IMAP Extension](https://www.php.net/manual/en/book.imap.php).**


### Troubleshooting

#### Joomla! Logs

To get debugging information from the Joomla! logs:

1. Enable [Log Almost
   Everything](https://docs.joomla.org/images/8/88/Debug_logging_settings-en.jpg)
   from the "Logging" tab of the "System - Debug" plugin in the [Extensions
   Plugin Manager](https://docs.joomla.org/Help310:Extensions_Plugin_Manager)?
2. Attempt to log in.
3. Open `administrator/logs/everything.php` from the Joomla! directory on your
   server in a text editor and search for `authentication_mailbox` near the end
   of the log file.  The log messages should include the arguments to
   [`imap_open`](https://www.php.net/manual/en/function.imap-open.php)
   (excluding passwords) and the resulting messages from
   [`imap_errors`](https://www.php.net/manual/en/function.imap-errors.php).
   - If `administrator/logs/everything.php` does not exist, check that the
     directory permissions allow the PHP process to write to that directory.
   - If `authentication_mailbox` does not appear in
     `administrator/logs/everything.php`, check that "Authentication -
     Mailbox" is enabled in the Extensions Plugin Manager.

#### imap_open

If [`imap_open`](https://www.php.net/manual/en/function.imap-open.php) can not
open the user's mailbox, it may be simpler to debug by calling
[`imap_open`](https://www.php.net/manual/en/function.imap-open.php) from a
test script such as the following:

```php
<?php
header('Content-Type: text/plain');

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

$mailbox = imap_open(
	'{imap.example.com/service=imap/tls/validate-cert}',
	'myusername',
	'mypassword',
	0,
	1);
print 'imap_open: ' . ($mailbox ? "Succeeded.\n" : "Failed.\n");

print 'imap_errors: ';
var_dump(imap_errors());

if ($mailbox) {
	imap_close($mailbox);
}
```

1. Save the above code into a file (e.g. `test.php`).
2. Adjust the
   [`imap_open`](https://www.php.net/manual/en/function.imap-open.php)
   parameters as necessary:
   1. Replace `imap.example.com` with a valid server (and replace
      `/service=imap` with `/sevice=pop3` or `/service=nntp` as necessary).
   2. Replace `myusername` and `mypassword` with the username and password with
      a valid user and adjust any other parameters as desired.
   3. Add or remove flags such as `OP_SECURE` from the flags parameter.
3. Upload the file to a web server.
4. Visit the URL for the uploaded file in a web browser.
5. Repeat steps 2-4 until the page contains:

       imap_open: Succeeded.
       imap_errors: bool(false)

6. Adjust the plugin configuration to match the functional parameters.

Installation instructions are available in [INSTALL.md](INSTALL.md).
Major changes are listed in [ChangeLog.txt](ChangeLog.txt).
Complete license text is available in [COPYING.txt](COPYING.txt).
