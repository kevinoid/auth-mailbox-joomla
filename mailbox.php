<?php
/**
 * Source file for the Authentication - Mailbox plugin
 *
 * @package		Auth_Mailbox
 * @copyright	Copyright (C) 2010 - 2011 Digital Engine Software, LLC. All rights reserved.
 * @copyright	Copyright (C) 2013-2014, 2016 Kevin Locke <kevin@kevinlocke.name>. All rights reserved.
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

jimport('joomla.log.log');
jimport('joomla.plugin.plugin');

/**
 * Mailbox Authentication Plugin
 *
 * @package	Auth_Mailbox
 * @since	1.0.0
 */
class PlgAuthenticationMailbox extends JPlugin
{
	/**
	 * Get the mailbox connection string for use in imap_open determined from
	 * the current param values.
	 *
	 * @return	string	Mailbox connection string for use in imap_open
	 *
	 * @access	private
	 */
	protected function getMailboxString()
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
	public function onUserAuthenticate($credentials, $options, &$response)
	{
		// Load translatable strings for this plugin
		$this->loadLanguage();

		$response->type = 'Mailbox';

		if (!function_exists('imap_open')) {
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message =
				JText::sprintf(
					'JGLOBAL_AUTH_FAILED',
					JText::_('ERRORIMAPNOTAVAIL')
				);
			// Important, not shown from error_message in all cases
			JError::raiseWarning(500, $response->error_message);
			return;
		}

		// Empty username/password can be interpreted as anonymous auth.
		if (empty($credentials['username']) || empty($credentials['password'])) {
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message =
				JText::sprintf(
					'JGLOBAL_AUTH_FAILED',
					JText::_('JGLOBAL_AUTH_EMPTY_PASS_NOT_ALLOWED')
				);
			return;
		}

		$username = $credentials['username'];
		$atpos = strpos($username, '@');

		if ($atpos === false) {
			$mailDomain = $this->params->get('mail_domain');

			if ($mailDomain) {
				$email = $username . '@' . $mailDomain;
			}
		} else {
			$email = $username;
			$username = substr($username, 0, $atpos);
		}

		$joomlaUsername =
			isset($email) && $this->params->get('mail_domain_in_joomla_username') ?
				$email :
				$username;
		$mailboxUsername =
			isset($email) && $this->params->get('mail_domain_username') ?
				$email :
				$username;

		// Check that the user exists, if required
		if (!$this->params->get('create_users')) {
			jimport('joomla.user.helper');

			if (!JUserHelper::getUserId($joomlaUsername)) {
				$response->status = JAuthentication::STATUS_FAILURE;
				$response->error_message =
					JText::sprintf(
						'JGLOBAL_AUTH_FAILED',
						JText::_('JGLOBAL_AUTH_NO_USER')
					);
				return;
			}
		}

		// Build mailbox options for imap_open
		$mailboxStr = $this->getMailboxString();
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

		JLog::add(
			JText::sprintf(
				'LOGIMAPOPEN',
				$mailboxStr,
				$mailboxUsername,
				$mailboxOpts
			),
			JLog::DEBUG
		);

		$mailboxStream = @imap_open(
			$mailboxStr,
			$mailboxUsername, $credentials['password'],
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

		JLog::add(
			JText::sprintf('LOGAUTHENTICATED', $joomlaUsername),
			JLog::DEBUG
		);

		$response->status = JAuthentication::STATUS_SUCCESS;
		$response->error_message = '';
		$response->fullname = $joomlaUsername;
		$response->username = $joomlaUsername;

		if (isset($email)) {
			$response->email = $email;
		}
	}
}

// vi: set sts=4 sw=4 ts=4 noet :
