Mailbox Authentication Plugin for Joomla!
=========================================

This is a Joomla! plugin to authenticate users against a mail server via IMAP,
NNTP, or POP3.  It forwards authentication credentials provided by the user to
a mail server, then relays the authentication decision of the mail server back
to Joomla!.

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
 * Supports the [Joomla! Update
   System](https://docs.joomla.org/Help36:Extensions_Extension_Manager_Update).


### Currently Unsupported Features

 * Does not integrate email/webmail into Joomla! or provide Single Sign-On
   (SSO) between email/webmail and Joomla!  Meaning users will still need to
   login to both Joomla! and email/webmail separately.
 * Authentication against multiple mail servers.  Currently this plugin only
   supports authentication against a single mail server (although the address
   to which it connects may be load-balanced to multiple servers transparently).


### Plugin Requirements

* **Joomla! 3.0 or later.**  (Confirmed working on Joomla! 4.0-alpha3.)
  There are previous versions of this plugin available [for Joomla!
  2.5](https://github.com/kevinoid/auth-mailbox-joomla/releases/tag/v1.0.9-for-joomla2.5)
  and [for Joomla!
  1.5](https://github.com/kevinoid/auth-mailbox-joomla/releases/tag/v1.0.9-for-joomla1.5).
* **[PHP IMAP Extension](https://secure.php.net/manual/en/book.imap.php).**

Installation instructions are available in [INSTALL.md](INSTALL.md).
Major changes are listed in [ChangeLog.txt](ChangeLog.txt).
Complete license text is available in [COPYING.txt](COPYING.txt).
