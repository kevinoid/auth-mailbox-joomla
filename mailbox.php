<?php
/**
 *
 * @version		$Id$
 * @package		Joomla
 * @subpackage	JFramework
 * @copyright	Copyright (C) 2010 Digital Engine Software, LLC. All rights reserved.
 * @license		GNU/GPL
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * Mailbox Authentication Plugin
 *
 * @package		Joomla
 * @subpackage	JFramework
 * @since 1.5
 */
class plgAuthenticationMailbox extends JPlugin
{
	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param	object	$subject	The object to observe
	 * @param	array	$config		An array that holds the plugin configuration
	 * @since	1.5
	 */
	function plgAuthenticationMailbox(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}

	function _getMailboxString( &$response )
	{
		$mailboxParts = array( '{' );
		$mailboxParts[] = $this->params->get( 'mail_server' );

		$port = $this->params->get( 'mail_port' );
		if (!empty( $port )) {
			$mailboxParts[] = ':';
			$mailboxParts[] = $port;
		}

		$mailboxParts[] = '/service=' . $this->params->get( 'mail_protocol' );
		$mailboxParts[] = '/readonly';

		switch ($this->params->get( 'mail_encryption' )) {
			case 0:
				$mailboxParts[] = '/notls';
				break;
			case 1:
				break;
			case 2:
				$mailboxParts[] = '/tls';
				break;
			case 3:
				$mailboxParts[] = '/ssl';
				break;
		}

		if (empty( $this->params->get( 'mail_allow_plaintext' )) ) {
			$mailboxParts[] = '/secure';
		}

		if (empty( $this->params->get( 'mail_validate_cert' )) ) {
			$mailboxParts[] = '/novalidate-cert';
		} else {
			$mailboxParts[] = '/validate-cert';
		}

		$mailboxParts[] = '}';

		return imap_utf7_encode( implode( '', $mailboxParts ) );
	}

	/**
	 * Handle authentication and report back to the subject
	 *
	 * @access	public
	 * @param	array	$credentials	Array holding the user credentials
	 * @param	array	$options		Array of extra options
	 * @param	object	$response		Authentication response object
	 * @return	boolean
	 * @since	1.5
	 */
	function onAuthenticate( $credentials, $options, &$response )
	{
		if (!function_exists( 'imap_open' )) {
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = JText::_( 'ERRORIMAPNOTAVAIL' );
			return;
		}

		// Empty username/password can be interpreted as anonymous auth.
		if (empty($credentials['username']) || empty($credentials['password'])) {
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = JText::_( 'ERROREMPTYUSER' );
			return;
		}

		$mailboxStr = $this->_getMailboxString( $response );
		$username = $credentials['username'];
		$domain = $this->params->get( 'mail_domain' );
		if (!empty( $domain )) {
			$email = $username . '@' . $domain;
			if (!empty( $this->params->get( 'mail_domain_username' ))) {
				$username = $email;
			}
		}

		$mailboxOpts = OP_READONLY;
		if (!empty( $this->params->get( 'mail_allow_plaintext' )) ) {
			$mailboxOpts |= OP_SECURE;
		}
		switch ($this->params->get( 'mail_protocol' )) {
			case 'imap':
			case 'nntp':
				$mailboxOpts |= OP_HALFOPEN;
				break;
		}

		// Clear error stack
		imap_errors();

		$mailboxStream = imap_open( $mailboxStr,
				$username, $credentials['password'],
				$mailboxOpts, 1 );

		if (!$mailboxStream) {
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = JText::sprintf( 'ERRORCONNECT',
				   implode( '\n', imap_errors() ) );
			return;
		}

		// Mailbox connection was successful, user authenticated
		imap_close($mailboxStream);

		$response->status = JAUTHENTICATE_STATUS_SUCCESS;
		$response->error_message = '';
		if (isset( $email )) {
			$response->email = $email;
		}
	}
}

// vi: set sts=4 sw=4 ts=4 noet :
