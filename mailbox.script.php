<?php
/**
 * Authentication - Mailbox installer script file
 *
 * @package		Auth_Mailbox
 * @copyright	Copyright (C) 2019 Kevin Locke <kevin@kevinlocke.name>. All rights reserved.
 * @license		GNU General Public License version 2 or later; see COPYING.txt
 * @since		1.0.10
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * Mailbox Authentication Plugin installer script
 *
 * @package	Auth_Mailbox
 * @since	1.0.10
 */
class PlgAuthenticationMailboxInstallerScript
{
	/**
	 * Called before any type of action
	 *
	 * @param	string				$route		Which action is happening (install|uninstall|discover_install|update).
	 * @param	JAdapterInstance	$adapter	The object responsible for running this script.
	 *
	 * @return	boolean	True on success
	 */
	public function preflight($route, $adapter)
	{
		if ($route !== 'install'
			&& $route !== 'discover_install'
			&& $route !== 'update')
		{
			// Allow any non-install/update operations unconditionally
			return true;
		}

		if (!function_exists('imap_open'))
		{
			$errorMessage = JText::_('PLG_AUTH_MAILBOX_PHP_IMAP_UNSATISFIED');

			JLog::add(
				$errorMessage,
				JLog::ERROR,
				'authentication_mailbox'
			);

			JFactory::getApplication()->enqueueMessage(
				htmlspecialchars($errorMessage),
				'error'
			);

			return false;
		}

		return true;
	}
}
