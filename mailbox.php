<?php
/**
 * Source file for the Authentication - Mailbox plugin
 *
 * @version		$Id$
 * @package		Auth_Mailbox
 * @copyright	Copyright (C) 2010 - 2011 Digital Engine Software, LLC. All rights reserved.
 * @license		GNU/GPL <http://www.gnu.org/licenses/gpl-2.0.html>
 * @since		1.0.0
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

/**
 * Mailbox Authentication Plugin
 *
 * @package	Auth_Mailbox
 * @since	1.0.0
 */
class plgAuthenticationMailbox extends JPlugin
{
	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args (void) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param	object	&$subject	The object to observe
	 * @param	array	$config		An array that holds the plugin configuration
	 *
	 * @since	1.5
	 */
	function plgAuthenticationMailbox(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage('', JPATH_ADMINISTRATOR);
	}

	/**
	 * Get the mailbox connection string for use in imap_open determined from
	 * the current param values.
	 *
	 * @return	string	Mailbox connection string for use in imap_open
	 *
	 * @access	private
	 */
	function _getMailboxString()
	{
		$mailboxParts = array('{');
		$mailboxParts[] = $this->params->get('mail_server');

		$port = $this->params->get('mail_port');
		if ($port) {
			$mailboxParts[] = ':';
			$mailboxParts[] = $port;
		}

		$protocol = $this->params->get('mail_protocol');
		$mailboxParts[] = '/service=' . $protocol;

		// Note:  /readonly only supported for IMAP, errors with SMTP & POP3
		if ($protocol === 'imap') {
			$mailboxParts[] = '/readonly';
		}

		switch ($this->params->get('mail_encryption')) {
			case 0:
				$mailboxParts[] = '/notls';
				break;
			case 2:
				$mailboxParts[] = '/tls';
				break;
			case 3:
				$mailboxParts[] = '/ssl';
				break;
			default:
				// For 1 (TLS Optional) and default case, no flag necessary
				break;
		}

		if (!$this->params->get('mail_allow_plaintext')) {
			$mailboxParts[] = '/secure';
		}

		if ($this->params->get('mail_validate_cert')) {
			$mailboxParts[] = '/validate-cert';
		} else {
			$mailboxParts[] = '/novalidate-cert';
		}

		$mailboxParts[] = '}';

		return imap_utf7_encode(implode('', $mailboxParts));
	}

	/**
	 * Handle authentication and report back to the subject
	 *
	 * @param	array	$credentials	Array holding the user credentials
	 * @param	array	$options		Array of extra options
	 * @param	object	&$response		Authentication response object
	 *
	 * @access	public
	 * @return	void
	 * @since	1.6
	 */
	function onUserAuthenticate($credentials, $options, &$response)
	{
		$response->type = 'Mailbox';

		if (!function_exists('imap_open')) {
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message = JText::_('ERRORIMAPNOTAVAIL');
			// Important, not shown from error_message in all cases
			JError::raiseWarning(500, $response->error_message);
			return;
		}

		// Empty username/password can be interpreted as anonymous auth.
		if (empty($credentials['username']) || empty($credentials['password'])) {
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message = JText::_('ERROREMPTYUSER');
			return;
		}

		// Check that the user exists, if required
		if (!$this->params->get('create_users')) {
			jimport('joomla.user.helper');

			if (!JUserHelper::getUserId($credentials['username'])) {
				$response->status = JAuthentication::STATUS_FAILURE;
				$response->error_message = JText::_('JGLOBAL_AUTH_NO_USER');
				return;
			}
		}

		$mailboxStr = $this->_getMailboxString();
		$username = $credentials['username'];
		$domain = $this->params->get('mail_domain');
		if ($domain) {
			$email = $username . '@' . $domain;
			if ($this->params->get('mail_domain_username')) {
				$username = $email;
			}
		}

		// Build mailbox options for imap_open
		$mailboxOpts = 0;
		$protocol = $this->params->get('mail_protocol');

		// Note:  OP_READONLY only supported for IMAP, errors with SMTP & POP3
		if ($protocol === 'imap') {
			$mailboxOpts |= OP_READONLY;
		}

		if (!$this->params->get('mail_allow_plaintext')) {
			$mailboxOpts |= OP_SECURE;
		}

		// Note:  OP_HALFOPEN only supported for IMAP and NNTP
		switch ($protocol) {
			case 'imap':
			case 'nntp':
				$mailboxOpts |= OP_HALFOPEN;
				break;
			default:
				break;
		}

		// Clear error stack
		imap_errors();

		$mailboxStream = @imap_open(
			$mailboxStr,
			$username, $credentials['password'],
			$mailboxOpts, 1
		);

		if (!$mailboxStream) {
			$response->status = JAuthentication::STATUS_FAILURE;

			$imapErrors = imap_errors();
			if (is_array($imapErrors) &&
					$this->params->get('show_imap_errors')) {
				$response->error_message = JText::sprintf(
					'ERRORCONNECTWITHMSG',
					implode('<br />', $imapErrors)
				);
				// Important, not shown from error_message in all cases
				JError::raiseWarning(500, $response->error_message);
			} else {
				$response->error_message = JText::_('ERRORCONNECT');
			}

			return;
		}

		// Mailbox connection was successful, user authenticated
		imap_close($mailboxStream);

		$response->status = JAuthentication::STATUS_SUCCESS;
		$response->error_message = '';
		if (isset($email)) {
			$response->email = $email;
		}
	}
}

// vi: set sts=4 sw=4 ts=4 noet :
