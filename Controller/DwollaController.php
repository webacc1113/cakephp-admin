<?php
App::uses('AppController', 'Controller');

class DwollaController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		
		App::import('Vendor', 'Dwolla');
		// OAuth parameters
		$redirectUri = Router::fullbaseUrl() .'/dwolla/';
		$permissions = array('AccountInfoFull', 'Send'); 

		// Instantiate a new Dwolla REST Client
		$Dwolla = new DwollaRestClient(DWOLLA_MASTER_KEY, DWOLLA_MASTER_SECRET, $redirectUri, $permissions);

		$get_code = isset($this->request->query['code']) ? $this->request->query['code'] : null;
		$get_error = isset($this->request->query['error']) ? $this->request->query['error'] : null;
		
		if (!$get_code && !$get_error) {
			$authUrl = $Dwolla->getAuthUrl();
			$this->redirect($authUrl);
		}
		
		if ($get_error) {
			$this->Session->setFlash("There was an error. Dwolla said: {$this->request->query['error_description']}", 'flash_error');
		}
		elseif ($get_code) {
			$token = $Dwolla->requestToken($get_code);
			if (!$token) {
				if ($error = $Dwolla->getError()) {;
					$error_msg = "There was an error. Dwolla said: {$error}";
				}
				else {
					$error_msg = "There was an error. ";
				}
				
				$this->Session->setFlash($error_msg."<br />Try again please.", 'flash_error');
			}
			else {
				$this->set('token', $token);
			}
		}
	}
}