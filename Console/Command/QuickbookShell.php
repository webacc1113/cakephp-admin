<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::uses('CakeEmail', 'Network/Email');
App::uses('Controller', 'Controller');
CakePlugin::load('Mailgun');

class QuickbookShell extends Shell {
	
	var $uses = array('Site', 'Invoice', 'Setting');
	var $data_service;
	function debug_logs() {
		$quickbook_logfile = APP . 'tmp' . DS . 'logs' . DS . 'quickbooks.log';
		if (file_exists($quickbook_logfile)) {
			$file = fopen($quickbook_logfile, 'r+');

			$messages = array();
			$logs = null;
			$current_date = time();
			$email = new CakeEmail();
			$email->config('smtp');
			if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
				if (defined('DEV_EMAIL')) {
					$email->to(unserialize(DEV_EMAIL));
				}
			}
			else {
				$email->to(unserialize(QUICKBOOK_DEBUG_LOG_EMAIL));
			}
			
			if ($file) {
				while (($line = fgets($file)) !== false) {
					$messages[] = $line;
				}
			}

			$messages = array_reverse($messages);
			foreach ($messages as $message) {
				$errros = explode(" Quickbooks: ", $message);
				if (!empty($errros[0]) && ($current_date - strtotime($errros[0]) <= 3600)) {
					$logs .= $message . "\n";
				}
				else {
					break;
				}
			}
			
			$email->from('no-reply@brandedresearchinc.com')
					->replyTo('no-reply@brandedresearchinc.com')
					->subject('Quickbook debug log console.');
			
			if ($logs && $email->send($logs)) { 
				fclose($file);
			}
		}
	}
	
	function invoice_sanity_check() {
		$this->init();
		$invoices = $this->data_service->Query("SELECT * FROM Invoice"); 
		if ($invoices) {
			$email_rows = array();
			$controller = new Controller();
			// grab the email template
			$template = 'log_email';
			$view = new View($controller, false);
			$view->layout = 'Emails/html/default';			
			$view->viewPath = 'Emails/html';			
			foreach ($invoices as $invoice) {
				if (!empty($invoice->LinkedTxn) && empty($invoice->Balance)) {
					continue; // paid
				}
				$db_invoice = $this->Invoice->find('first', array(
					'conditions' => array(
						'Invoice.quickbook_invoice_id' => $invoice->Id
					)
				));
				if (!$db_invoice) {
					$email_rows[] = array(
						'quickbook_invoice_id' =>  $invoice->Id,
						'invoice_id' => 0,
						'QB Balance' => $invoice->Balance,
						'MV Balance' => '-',
						'description' => 'Invoice not found on MV'
					);
				}
				else {
					if ($db_invoice['Invoice']['subtotal'] != $invoice->Balance) {
						$email_rows[] = array(
							'quickbook_invoice_id' =>  $invoice->Id,
							'project_id' => $db_invoice['Invoice']['project_id'],
							'invoice_id' => $db_invoice['Invoice']['id'],
							'QB Balance' => $invoice->Balance,
							'MV Balance' => $db_invoice['Invoice']['subtotal'],
							'description' => 'Invoice Amount Mismatch'
						);
					}
				}
			}
			$invoices = $this->Invoice->find('all', array(
				'conditions' => array(
					'Invoice.quickbook_invoice_id is null',
					'Invoice.created >=' => date(DB_DATETIME, strtotime('-1 week'))
				)
			));
			if ($invoices) {
				foreach ($invoices as $invoice) {
					$email_rows[] = array(
						'quickbook_invoice_id' => 0,
						'invoice_id' => $invoice['Invoice']['project_id'],
						'QB Balance' => '-',
						'MV Balance' => $invoice['Invoice']['subtotal'],
						'description' => 'Invoice not found on QB'
					);
				}
			}
			if (!empty($email_rows)) {
				$view->set(compact('email_rows'));
				$email_body = $view->render($template);
				$this->autoRender = true;			
				$email = new CakeEmail();
				$email->config('mailgun');
				$setting = $this->Setting->find('first', array(
					'conditions' => array(
						'Setting.name' => 'quickbook_debug_log_email',
						'Setting.deleted' => false
					)
				));
				$log_emails = array();
				if ($setting) {
					$log_emails = explode(',', $setting['Setting']['value']);
					foreach ($log_emails as $key => $log_email) {
						if (!Validation::email($log_email)) {
							unset($log_emails[$key]);
						}
					}
				}
				$email->from(array(EMAIL_SENDER => 'MintVine'))
					->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
					->emailFormat('html')
				    ->to($log_emails)
				    ->subject('Mismatched Invoice Report');
				$response = $email->send($email_body);
				echo 'Sent';
			}
		}
	}
	
	function init() {		
		$site = $this->Site->find('first', array(
			'conditions' => array(
				'Site.path_name' => QUICKBOOK_API_PATH_NAME
			)
		));
		if ($site) {
			require_once(APP.'Vendor/Quickbook/config.php');
			App::import('Vendor', 'QuickbookServiceContext', array('file' => 'Quickbook/Core/ServiceContext.php'));
			App::import('Vendor', 'QuickbookDataService', array('file' => 'Quickbook/DataService/DataService.php'));
			App::import('Vendor', 'QuickbookPlatformService', array('file' => 'Quickbook/PlatformService/PlatformService.php'));
			App::import('Vendor', 'QuickbookConfigurationManager', array('file' => 'Quickbook/Utility/Configuration/ConfigurationManager.php'));
			if (empty($site['Site']['oauth_tokens'])) {
				return;
			}
			$auth_settings = json_decode($site['Site']['oauth_tokens']);		
			$service_type = IntuitServicesType::QBO;
			// Prep Service Context
			$request_validator = new OAuthRequestValidator(
				$auth_settings->oauth_token,
				$auth_settings->oauth_token_secret,
				$site['Site']['api_key'],
				$site['Site']['api_secret']
			);
			$service_context = new ServiceContext($auth_settings->realmId, $service_type, $request_validator);
			$this->data_service = new DataService($service_context);
		}
	}
}