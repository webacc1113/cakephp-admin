<?php
/**
 * AppShell file
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Shell', 'Console');

/**
 * Application Shell
 *
 * Add your application-wide methods in the class below, your shells
 * will inherit them.
 *
 * @package       app.Console.Command
 */
class AppShell extends Shell {

	function initialize() {
		parent::initialize();
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		
		$settings = $this->Setting->find('all', array(
			'conditions' => array(
				'Setting.name' => array(
					'hostname.redirect', 
					'hostname.www', 
					'hostname.web', 
					'hostname.api', 
					'site.email_sender', 
					'site.reply_to_email',
					'dwolla.master.key',
					'dwolla.master.secret',
					'dwolla.master.pin'
				),
				'Setting.deleted' => false
			)
		));
		
		if ($settings) {
			foreach ($settings as $setting) {
				if ($setting['Setting']['name'] == 'hostname.redirect' && !defined('HOSTNAME_REDIRECT')) {
					define('HOSTNAME_REDIRECT', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'hostname.www' && !defined('HOSTNAME_WWW')) {
					define('HOSTNAME_WWW', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'hostname.web' && !defined('HOSTNAME_WEB')) {
					define('HOSTNAME_WEB', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'hostname.api' && !defined('HOSTNAME_API')) {
					define('HOSTNAME_API', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'site.email_sender' && !defined('EMAIL_SENDER')) {
					define('EMAIL_SENDER', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'site.reply_to_email' && !defined('REPLYTO_EMAIL')) {
					define('REPLYTO_EMAIL', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'dwolla.master.key' && !defined('DWOLLA_MASTER_KEY')) {
					define('DWOLLA_MASTER_KEY', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'dwolla.master.secret' && !defined('DWOLLA_MASTER_SECRET')) {
					define('DWOLLA_MASTER_SECRET', $setting['Setting']['value']);
				}
				elseif ($setting['Setting']['name'] == 'dwolla.master.pin' && !defined('DWOLLA_MASTER_PIN')) {
					define('DWOLLA_MASTER_PIN', $setting['Setting']['value']);
				}
			}
		}
	}
	
	function lecho($message, $key, $logging_key = null) {
		if (empty($message)) {
			return;
		}
		if (is_array($message)) {
			if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
				CakeLog::write($key, $message);
			}
			else {
				CakeLog::write($key, print_r($message, true));
			}
			return;
		}
		$output = '';
		if (!empty($logging_key)) {
			$output = '['.$logging_key.'] ';
		}
		$output .= $message;
		echo $output."\n";
		CakeLog::write($key, $output);
	}
}
