<?php
/**
 * Source file for the Authentication - Mailbox plugin
 *
 * @package		Auth_Mailbox
 * @copyright	Copyright (C) 2010 - 2011 Digital Engine Software, LLC. All rights reserved.
 * @copyright	Copyright (C) 2013-2014, 2016, 2019 Kevin Locke <kevin@kevinlocke.name>. All rights reserved.
 * @license		GNU General Public License version 2 or later; see COPYING.txt
 * @since		1.0.0
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

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

		if ($port)
		{
			$mailboxParts[] = ':';
			$mailboxParts[] = $port;
		}

		$protocol = $this->params->get('mail_protocol');
		$mailboxParts[] = '/service=' . $protocol;

		// Note:  /readonly only supported for IMAP, errors with SMTP & POP3
		if ($protocol === 'imap')
		{
			$mailboxParts[] = '/readonly';
		}

		switch ($this->params->get('mail_encryption'))
		{
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

		if (!$this->params->get('mail_allow_plaintext'))
		{
			$mailboxParts[] = '/secure';
		}

		if ($this->params->get('mail_validate_cert'))
		{
			$mailboxParts[] = '/validate-cert';
		}
		else
		{
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
	 * @param	object	$response		Authentication response object
	 *
	 * @access	public
	 * @return	void
	 */
	public function onUserAuthenticate($credentials, $options, &$response)
	{
		// Load translatable strings for this plugin
		$this->loadLanguage();

		$response->type = 'Mailbox';

		if (!function_exists('imap_open'))
		{
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message = JText::sprintf(
				'JGLOBAL_AUTH_FAILED',
				JText::_('PLG_AUTH_MAILBOX_ERRORIMAPNOTAVAIL')
			);

			JLog::add(
				$response->error_message,
				JLog::ERROR,
				'authentication_mailbox'
			);

			/*
			 * Alert user to misconfiguration, since
			 * Authentication->authenticate only returns AuthenticationResponse
			 * for the last plugin and CMSApplication->login only displays it
			 * when $options['silent'] is falsey.  Better to see 2 copies than
			 * none.
			 */
			JFactory::getApplication()->enqueueMessage(
				htmlspecialchars($response->error_message),
				'warning'
			);

			return;
		}

		// Empty username/password can be interpreted as anonymous auth.
		if (empty($credentials['username']) || empty($credentials['password']))
		{
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message = JText::sprintf(
				'JGLOBAL_AUTH_FAILED',
				JText::_('JGLOBAL_AUTH_EMPTY_PASS_NOT_ALLOWED')
			);

			return;
		}

		$username = $credentials['username'];
		$atpos = strpos($username, '@');

		if ($atpos === false)
		{
			$mailDomain = $this->params->get('mail_domain');

			if ($mailDomain)
			{
				$email = $username . '@' . $mailDomain;
			}
		}
		else
		{
			$email = $username;
			$username = substr($username, 0, $atpos);
		}

		$joomlaUsername
			= isset($email) && $this->params->get('mail_domain_in_joomla_username') ?
				$email :
				$username;
		$mailboxUsername
			= isset($email) && $this->params->get('mail_domain_username') ?
				$email :
				$username;

		// Check that the user exists, if required
		if (!$this->params->get('create_users')
			&& !JUserHelper::getUserId($joomlaUsername))
		{
			$response->status = JAuthentication::STATUS_FAILURE;
			$response->error_message = JText::sprintf(
				'JGLOBAL_AUTH_FAILED',
				JText::_('JGLOBAL_AUTH_NO_USER')
			);

			return;
		}

		// Build mailbox options for imap_open
		$mailboxStr = $this->getMailboxString();
		$mailboxOpts = 0;
		$protocol = $this->params->get('mail_protocol');

		// Note:  OP_READONLY only supported for IMAP, errors with SMTP & POP3
		if ($protocol === 'imap')
		{
			$mailboxOpts |= OP_READONLY;
		}

		if (!$this->params->get('mail_allow_plaintext'))
		{
			$mailboxOpts |= OP_SECURE;
		}

		// Note:  OP_HALFOPEN only supported for IMAP and NNTP
		switch ($protocol)
		{
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
				'PLG_AUTH_MAILBOX_LOGIMAPOPEN',
				$mailboxStr,
				$mailboxUsername,
				$mailboxOpts
			),
			JLog::DEBUG,
			'authentication_mailbox'
		);

		$mailboxStream = @imap_open(
			$mailboxStr,
			$mailboxUsername, $credentials['password'],
			$mailboxOpts, 1
		);

		if (!$mailboxStream)
		{
			$response->status = JAuthentication::STATUS_FAILURE;
			$imapErrors = imap_errors() ?: array();

			$errorMessage = JText::sprintf(
				'PLG_AUTH_MAILBOX_ERRORCONNECTWITHMSG',
				implode("\n", $imapErrors)
			);

			// Multi-line log messages can be problematic in log files.  Use ;
			$errorMessageForLog = str_replace("\n", '; ', $errorMessage);
			JLog::add(
				$errorMessageForLog,
				JLog::WARNING,
				'authentication_mailbox'
			);

			if ($this->params->get('show_imap_errors'))
			{
				$response->error_message = $errorMessageForLog;

				/*
				 * Display error to user user as requested by configuration.
				 * Note:  Authentication->authenticate only returns
				 * AuthenticationResponse for the last plugin and
				 * CMSApplication->login only displays it when
				 * $options['silent'] is falsey.  Better to see 2 copies than
				 * none.
				 */
				JFactory::getApplication()->enqueueMessage(
					str_replace("\n", '<br />', htmlspecialchars($errorMessage)),
					'warning'
				);
			}
			else
			{
				$response->error_message
					= JText::_('PLG_AUTH_MAILBOX_ERRORCONNECT');
			}

			return;
		}

		// Mailbox connection was successful, user authenticated
		imap_close($mailboxStream);

		JLog::add(
			JText::sprintf('PLG_AUTH_MAILBOX_LOGAUTHENTICATED', $joomlaUsername),
			JLog::DEBUG,
			'authentication_mailbox'
		);

		$response->status = JAuthentication::STATUS_SUCCESS;
		$response->error_message = '';
		$response->fullname = $joomlaUsername;
		$response->username = $joomlaUsername;

		if (isset($email))
		{
			$response->email = $email;
		}
	}
}
