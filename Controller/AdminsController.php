<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class AdminsController extends AppController {

	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('login', 'forgot_password', 'verify_phone_number', 'verify_email', 'verify_authy_request', 'check_authy_request_status');
	}
	
	public function index() {
		$this->Admin->AdminRole->bindModel(array('belongsTo' => array('Role')));
		$this->Admin->AdminGroup->bindModel(array('belongsTo' => array('Group')));
		if (isset($this->request->query['group_id']) && !empty($this->request->query['group_id'])) {
			$this->data = $this->request->query;
			$this->Admin->AdminGroup->bindModel(array('belongsTo' => array('Admin')));
			$paginate = array(
				'AdminGroup' => array(
					'contain' => array(
						'Admin' => array(
							'AdminRole' => array(
								'Role'
							),
							'AdminGroup' => array(
								'Group'
							)
						),
					),
					'limit' => 50,
					'order' => 'AdminGroup.admin_id',
					'recursive' => 2,
					'conditions' => array(
						'AdminGroup.group_id' => $this->request->query['group_id']
					)
				)
			);

			$this->paginate = $paginate;
			$admins = $this->paginate('AdminGroup');
			foreach ($admins as &$admin) {
				$admin['AdminRole'] = $admin['Admin']['AdminRole'];
				unset($admin['Admin']['AdminRole']);
				$admin['AdminGroup'] = $admin['Admin']['AdminGroup'];
				unset($admin['Admin']['AdminGroup']);
			}
		}
		elseif (isset($this->request->query['role_id']) && !empty($this->request->query['role_id'])) {
			$this->data = $this->request->query;
			$this->Admin->AdminRole->bindModel(array('belongsTo' => array('Admin')));
			$paginate = array(
				'AdminRole' => array(
					'contain' => array(
						'Admin' => array(
							'AdminRole' => array(
								'Role'
							),
							'AdminGroup' => array(
								'Group'
							)
						),
					),
					'limit' => 50,
					'order' => 'AdminRole.admin_id',
					'recursive' => 2,
					'conditions' => array(
						'AdminRole.role_id' => $this->request->query['role_id']
					)
				)
			);

			$this->paginate = $paginate;
			$admins = $this->paginate('AdminRole');
			foreach ($admins as &$admin) {
				$admin['AdminRole'] = $admin['Admin']['AdminRole'];
				unset($admin['Admin']['AdminRole']);
				$admin['AdminGroup'] = $admin['Admin']['AdminGroup'];
				unset($admin['Admin']['AdminGroup']);
			}
		}
		else {
			$paginate = array(
				'Admin' => array(
					'conditions' => array(
						'Admin.key is null' //key based admins are for logging purposes
					),
					'limit' => 50,
					'order' => 'Admin.id ASC',
					'recursive' => 2
				)
			);

			$this->paginate = $paginate;
			$admins = $this->paginate('Admin');
		}
		
		$this->loadModel('Group');
		$groups = $this->Group->find('list', array(
			'order' => 'Group.name ASC'
		));
		$this->loadModel('Role');
		$roles = $this->Role->find('list', array(
			'order' => 'Role.name ASC'
		));
		$this->set(compact('groups', 'roles', 'admins'));
	}
	
	public function add() {
		if ($this->request->is('post')) {
			$this->request->data['Admin']['active'] = true;
			
			$this->request->data['Admin']['limit_access'] = trim($this->request->data['Admin']['limit_access']);
			$this->request->data['Admin']['limit_access'] = $original_limit_access = trim($this->request->data['Admin']['limit_access']);
			if (!empty($this->request->data['Admin']['limit_access'])) {
				$limit_access = preg_split('/\R/', $this->request->data['Admin']['limit_access']);
				$this->request->data['Admin']['limit_access'] = json_encode($limit_access);
			}			
			if (empty($this->request->data['Admin']['limit_access'])) {
				unset($this->request->data['Admin']['limit_access']);
			}
			if (empty($this->request->data['Admin']['autenticate_type'])) {
				$this->request->data['Admin']['autenticate_type'] = null;
			}
				
			$this->Admin->create();
            if ($this->Admin->save($this->request->data)) {				
				$this->Session->setFlash(__('Admin has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
			else {
				$this->request->data['Admin']['limit_access'] = $original_limit_access;
			}
			
            $this->Session->setFlash(__('Unable to add the Admin.'), 'flash_error');
        }
		
		$this->loadModel('Group');
		$this->loadModel('Role');
		$this->loadModel('Project');
		$groups = $this->Group->find('list', array(
			'order' => 'Group.name ASC'
		));
		$roles = $this->Role->find('list', array(
			'fields' => array('Role.id', 'Role.name')
		));
		$role_keys = $this->Role->find('list', array(
			'fields' => array('Role.key', 'Role.id')
		));
		$this->set(compact('groups', 'roles', 'role_keys'));
	}
	
	public function edit($id) {
		$admin = $this->Admin->findById($id);
    	if (!$admin) {
        	throw new NotFoundException(__('Invalid Admin'));
    	}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->request->data['Admin']['active'] = true;
			if (!$this->request->data['Admin']['admin_pass']) {
				unset($this->request->data['Admin']['admin_pass']);
			}
			
			if ($this->request->data['Admin']['phone_number'] != $admin['Admin']['phone_number'] || $this->request->data['Admin']['phone_country_code'] != $admin['Admin']['phone_country_code']) {
				$this->request->data['Admin']['authy_user_id'] = null;
			}	
			$this->request->data['Admin']['limit_access'] = $original_limit_access = trim($this->request->data['Admin']['limit_access']);
			if (!empty($this->request->data['Admin']['limit_access'])) {
				$limit_access = preg_split('/\R/', $this->request->data['Admin']['limit_access']);
				$this->request->data['Admin']['limit_access'] = json_encode($limit_access);
			}
			
			if (empty($limit_access)) {
				unset($this->request->data['Admin']['limit_access']);
			}
			if (empty($this->request->data['Admin']['authenticate_type']) || empty($this->request->data['Admin']['phone_number'])) {
				$this->request->data['Admin']['authenticate_type'] = null;
			}
					
			if ($this->Admin->save($this->request->data)) {
        	    $this->Session->setFlash(__('Admin has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
			else {
				$this->request->data['Admin']['limit_access'] = $original_limit_access;
			}
			
        	$this->Session->setFlash(__('Unable to update the Admin.'), 'flash_error');
    	}
		
    	if (!$this->request->data) {
        	$this->request->data = $admin;
    	}
		
		$selected_groups = array();
		if (!empty($admin['AdminGroup'])) {
			foreach ($admin['AdminGroup'] as $group) {
				$selected_groups[] = $group['group_id'];
			}
		}
		
		$selected_roles = array();
		if (!empty($admin['AdminRole'])) {
			foreach ($admin['AdminRole'] as $role) {
				$selected_roles[] = $role['role_id'];
			}
		}
		
		$this->loadModel('Group');
		$this->loadModel('Role');
		$this->loadModel('Project');
		$groups = $this->Group->find('list', array(
			'order' => 'Group.name ASC'
		));
		$roles = $this->Role->find('list', array(
			'fields' => array('Role.id', 'Role.name')
		));
		$role_keys = $this->Role->find('list', array(
			'fields' => array('Role.key', 'Role.id')
		));
		
		$this->set(compact('groups', 'roles', 'role_keys', 'selected_groups', 'selected_roles', 'admin'));
	}
	
	public function active() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$admin = $this->Admin->findById($id);
			$active = ($admin['Admin']['active']) ? 0 : 1;
    		$this->Admin->save(array('Admin' => array(
    			'id' => $id,
				'active' => $active,
			)), true, array('active'));
			
    		return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $active
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function delete() {
		if ($this->request->is('post') || $this->request->is('put')) {
    		$id = $this->request->data['id'];
			$admin = $this->Admin->findById($id);
			$this->Admin->delete($id);
    		return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)), 
				'type' => 'json',
				'status' => '201'
			));
    	}
	}
	
	public function forgot_password() {
		if (!empty($this->request->data) && isset($this->request->data['Admin']['admin_user'])) {
			$admin = $this->Admin->findByAdminUser($this->request->data['Admin']['admin_user']);
			if (!$admin) {
				$this->Session->setFlash('We couldn\'t locate that username.', 'flash_error');
				$this->redirect(array('action' => 'admin_forgot_password'));
			}
			if (empty($admin['Admin']['admin_email'])) {
				$this->Session->setFlash('That user does not have an email address associated with it.', 'flash_error');
			} else {
				$admin_pass_temp = Utils::rand(6);
				$this->Admin->create();
				$this->Admin->save(array('Admin' => array(
					'id' => $admin['Admin']['id'],
					'admin_pass_temp' => $admin_pass_temp
				)), true, array('admin_pass_temp'));
				
				$setting = $this->Setting->find('list', array(
					'fields' => array('Setting.name', 'Setting.value'),
					'conditions' => array(
						'Setting.name' => array('cdn.url'),
						'Setting.deleted' => false
					)
				));
				if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
					Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
					Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
					Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
				}
				
				$email = new CakeEmail();
				$email->config('mailgun');
				$result = $email->from(array(EMAIL_SENDER => 'MintVine'))
					->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
					->template('admin_forgot_password')
					->viewVars(array('admin' => $admin, 'admin_pass_temp' => $admin_pass_temp))
					->emailFormat('html')
					->to($admin['Admin']['admin_email'])
					->subject('MintVine Reset Password')
			   		->send();					
				if ($result) {
					$this->Session->setFlash(__('An email has been sent with temporary password.'), 'flash_success');
					$this->redirect(array('action' => 'login'));
				} else {
					$this->Session->setFlash(__('An error occurred. Please try again.'), 'flash_error');
				}
			}
		}
	}
	
	public function login() {
		if ($this->request->is('post') || $this->request->is('put')) {

			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('twilio.account_sid', 'twilio.auth_token', 'twilio.phone_numbers', 'twilio.verification_templates', 'twilio.sms.endpoint', 'twilio.lookup.endpoint', 'authy.active', 'authy.api.key', 'authy.login.request.message'),
					'Setting.deleted' => false
				)
			));
			$admin = $this->Admin->find('first', array(
				'conditions' => array(
					'Admin.admin_user' => $this->request->data['Admin']['admin_user'],
					'Admin.active' => true,
					'Admin.key' => null //key based admins are for logging purposes
				)
			));
			$authorized = false;
			
			if (!$admin) {
				$authorized = false;
			}
			elseif (sha1($this->request->data['Admin']['admin_pass']) == $admin['Admin']['admin_pass']) {
				$authorized = true;
			} 
			elseif (AuthComponent::password($this->request->data['Admin']['admin_pass']) == $admin['Admin']['admin_pass_temp']) {
				$authorized = true;
			}
			if ($authorized) {
				if (isset($settings['authy.active']) && $settings['authy.active'] == 'true' && !empty($admin['Admin']['phone_number']) && !empty($admin['Admin']['authy_user_id']) && $admin['Admin']['authenticate_type'] == 'custom_code') {
					try {
						$http = new HttpSocket(array(
							'timeout' => 30,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						$result = $http->post('https://api.authy.com/onetouch/json/users/'.$admin['Admin']['authy_user_id'].'/approval_requests',
							array(
								'api_key' => $settings['authy.api.key'],
								'message' => $settings['authy.login.request.message'],
								'seconds_to_expire' => 600
							)
						);
						$result_body = json_decode($result['body'], true);
						
						if ($result_body['success']) {
							$this->Admin->create();
							$this->Admin->save(array('Admin' => array(
								'id' => $admin['Admin']['id'],							
								'authy_uuid' => $result_body['approval_request']['uuid'],						
							)), array(
								'validate' => false, 
								'callbacks' => false, 
								'fieldList' => array(
									'authy_uuid'
								)
							));
							$this->Session->write('authy-2-factor-authentication', $admin['Admin']['id']);
							$this->Session->setFlash('Request has been sent to your Authy mobile app.', 'flash_success');
							$this->redirect(array('controller' => 'admins', 'action' => 'verify_authy_request'));
						}
						else {
							$this->Session->setFlash($result_body['message'], 'flash_error');
							$this->redirect(array('controller' => 'admins', 'action' => 'login'));
						}
					}
					catch (Exception $e) {
						$this->Session->setFlash($e->getMessage(), 'flash_error');
						$this->redirect(array('controller' => 'admins', 'action' => 'login'));
					}	
				} 
				elseif (!empty($admin['Admin']['phone_number']) && isset($settings['twilio.account_sid']) && $admin['Admin']['authenticate_type'] == 'sms') {
					try {
						$rand = rand('1000', '9999');
						$verification_code = $rand . '-' . time();
						$HttpSocket = new HttpSocket(array(
							'timeout' => 15,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));

						// choose random phone number
						$phone_numbers = explode(',', $settings['twilio.phone_numbers']);
						$real_phone_number = $phone_numbers[mt_rand(0, count($phone_numbers) - 1)];

						// choose random verification template
						$verification_templates = json_decode($settings['twilio.verification_templates'], true);
						$verification_template = $verification_templates[mt_rand(0, count($verification_templates) - 1)];

						// Twillo SMS StatusCallback URL
						$status_callback = 'https://api.mintvine.com/verification/status/' . $admin['Admin']['id'];

						$HttpSocket->configAuth('Basic', $settings['twilio.account_sid'], $settings['twilio.auth_token']);
						$results = $HttpSocket->post(str_replace('[SID]', $settings['twilio.account_sid'], $settings['twilio.sms.endpoint']),
							array(
								'To' => $admin['Admin']['phone_number'],
								'From' => $real_phone_number,
								'Body' => str_replace('[VERIFICATION_CODE]', $rand, $verification_template),
								'StatusCallback' => $status_callback
							)
						);

						$this->Admin->create();
						$this->Admin->save(array('Admin' => array(
							'id' => $admin['Admin']['id'],							
							'mobile_verification_code' => $verification_code,						
						)), array(
							'validate' => false, 
							'callbacks' => false, 
							'fieldList' => array(
								'mobile_verification_code'
							)
						));
						$this->Session->write('2-factor-authentication', $admin['Admin']['id']);
						$this->Session->setFlash('If you do not get your SMS within the next 30 seconds please check your email.', 'flash_success');
						$this->redirect(array('controller' => 'admins', 'action' => 'verify_phone_number'));
					}
					catch (Exception $e) {

					}
				}

				if ($this->Auth->login($admin)) {
					$this->Session->setFlash('You have been successfully logged in.', 'flash_success');
					$this->redirect($this->Auth->redirect());
					return;
				}
			}
			else {
				$this->Session->setFlash('You input the wrong password.', 'flash_error');
			}
			$this->redirect($this->Auth->loginAction);
		}
	}
		
	public function logout() {
		$this->Session->destroy();
		$this->Session->setFlash(__('Log out successful.'), 'flash_success');
		$this->redirect($this->Auth->logout());
	}
	
	function verify_phone_number() {
		$admin_id = $this->Session->read('2-factor-authentication');
		if (empty($admin_id)) {
			$this->redirect(array('controller' => 'admins', 'action' => 'login'));
		}
		if ($this->request->is(array('put', 'post'))) {
			$admin = $this->Admin->find('first', array(
				'conditions' => array(
					'Admin.id' => $admin_id
				)
			));
			if ($admin) {
				$db_verificaton_code = $admin['Admin']['mobile_verification_code'];
				$db_verificaton_code = explode('-', $db_verificaton_code);
				if (!empty($db_verificaton_code[0]) && !empty($db_verificaton_code[1])) {
					$verificaton_code = $db_verificaton_code[0];
					$code_sent_time = $db_verificaton_code[1];
					if ($verificaton_code == $this->request->data['Admin']['mobile_verification_code']) {	
						if (time() <= $code_sent_time + 600) {
							$this->Admin->create();
							$this->Admin->save(array('Admin' => array(
								'id' => $admin['Admin']['id'],
								'mobile_verification_code' => null,									
							)), false, array('mobile_verification_code'));	

							if ($this->Auth->login($admin)) {
								$this->Session->delete('2-factor-authentication');
								$this->Session->setFlash('You have been successfully logged in.', 'flash_success');
								$this->redirect($this->Auth->redirect());
								return;
							}								
						}
						else {
							$this->Session->setFlash(__('Verification code is expired.'), 'flash_error');
							$this->redirect(array('controller' => 'admins', 'action' => 'index'));
						}
					}
					else {
						$this->Session->setFlash(__('Invalid verification code.'), 'flash_error');
					}
				}
				else {
					$this->Session->setFlash(__('Invalid verification code.'), 'flash_error');
				}
			}
			else {
				$this->redirect(array('controller' => 'admins', 'action' => 'login'));
			}
		}
	}

	public function verify_email($original_nonce = null) {
		$this->loadModel('AdminNonce');
		if (empty($original_nonce)) {
			throw new NotFoundException();
		}

		$admin_nonce = $this->AdminNonce->find('first', array(
			'conditions' => array(
				'AdminNonce.nonce' => $original_nonce,
				'AdminNonce.expires >=' => date(DB_DATETIME)
			)
		));

		// silently succeed
		if (!$admin_nonce || !empty($admin_nonce['AdminNonce']['used'])) {
			$this->Session->setFlash(__('That link in your email was not valid. Please request another login code.'), 'flash_error');
			$this->redirect(array('controller' => 'admins', 'action' => 'login'));
		}

		// grab admin
		$admin = $this->Admin->find('first', array(
			'conditions' => array(
				'Admin.id' => $admin_nonce['AdminNonce']['admin_id']
			)
		));
		if ($admin) {
			if ($this->Auth->login($admin)) {
				$this->AdminNonce->create();
				$this->AdminNonce->save(array('AdminNonce' => array(
					'id' => $admin_nonce['AdminNonce']['id'],
					'used' => date(DB_DATETIME)
				)), true, array('used'));

				$this->Session->setFlash('You have been successfully logged in.', 'flash_success');
				$this->redirect($this->Auth->redirect());
				return;
			}
		}
		else {
			$this->Session->setFlash(__('That link in your email was not valid. Please request another login code.'), 'flash_error');
			$this->redirect(array('controller' => 'admins', 'action' => 'login'));
		}
	}

	public function ajax_save_permission () {
		if ($this->request->is(array('put', 'post', 'ajax'))) {
			if ($this->request->data['checked']) {
				$this->Admin->AdminRole->create();
				$this->Admin->AdminRole->save(array('AdminRole' => array(
					'admin_id' => $this->request->data['admin_id'],
					'role_id' => $this->request->data['role_id'],
				)));
			}
			else {
				$admin_role = $this->Admin->AdminRole->find('first', array(
					'fields' => array('AdminRole.id'),
					'conditions' => array(
						'admin_id' => $this->request->data['admin_id'],
						'role_id' => $this->request->data['role_id'],
					)
				));
				
				if ($admin_role) {
					$this->Admin->AdminRole->delete($admin_role['AdminRole']['id']);
				}
			}
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => '0'
			)), 
			'type' => 'json',
			'status' => '401'
		));
	}
	
	function preferences() {		
		$user = $this->Admin->find('first', array(
			'conditions' => array(
				'Admin.id' => $this->current_user['Admin']['id']
			),
			'id', 'admin_email', 'authy_user_id', 'country_code', 'phone_country_code', 'phone_number', 'timezone'
		));
		
		if (!$user) {
			throw new NotFoundException();
		}
		
		if ($this->request->is(array('put', 'post'))) {
			if (!$this->request->data['Admin']['admin_pass']) {
				unset($this->request->data['Admin']['admin_pass']);
			}
			
			$this->request->data['Admin']['authy_user_id'] = $user['Admin']['authy_user_id'];
			if ($this->request->data['Admin']['phone_number'] != $user['Admin']['phone_number'] || $this->request->data['Admin']['phone_country_code'] != $user['Admin']['phone_country_code']) {
				$this->request->data['Admin']['authy_user_id'] = null;
			}
			
			$this->Admin->validate = array(				
				'admin_email' => array(
					'rule' => array('email', true),
					'message' => 'Please supply a valid email address.'
				),
				'admin_pass' => array(
					'rule' => array('minLength', 6),
					'message' => 'Passwords must be at least 6 characters long.'
				),
				'timezone' => array(
					'rule' => 'notEmpty',
					'message' => 'Select your timezone please!'
				)
			);
			$this->Admin->create();
			$save = $this->Admin->save($this->request->data, true, array('admin_email', 'admin_pass', 'slack_username', 'country_code', 'phone_country_code', 'phone_number', 'authy_user_id', 'timezone'));
            if ($save) {
				$this->Session->setFlash(__('Preferences has updated saved.'), 'flash_success');
                return $this->redirect(array('action' => 'preferences'));
            }
            $this->Session->setFlash(__('Unable to update preferences.'), 'flash_error');
		}	
		else {
			$this->request->data = $user;
		}
	}

	public function set_authenticate_type() {
		if ($this->request->is('Post')) {
			$this->request->data['active'] = true;
			if (empty($this->request->data['authenticate_type'])) {
				$this->request->data['authenticate_type'] = null;
			}
			$this->Admin->create();
			$this->Admin->save(array('Admin' => $this->request->data), true, array('authenticate_type'));
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'ok'
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function verify_authy_request() {
		$admin_id = $this->Session->read('authy-2-factor-authentication');
		if (empty($admin_id)) {
			$this->redirect(array('controller' => 'admins', 'action' => 'login'));
		}	
		$setting_names = array(
			'authy.active', 
			'authy.api.key'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $setting_names,
				'Setting.deleted' => false
			)
		));
		if (count($settings) < count($setting_names)) {
			$this->Session->setFlash('Authy Settings not found.', 'flash_error');
			$this->redirect(array('controller' => 'admins', 'action' => 'login'));
		}
		
		if ($this->request->is(array('put', 'post'))) {
			$admin = $this->Admin->findById($admin_id);
			try {
				$http = new HttpSocket(array(
					'timeout' => 30,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));

				$result = $http->get('https://api.authy.com/protected/json/verify/'.$this->request->data['Admin']['authy_token'].'/'.$admin['Admin']['authy_user_id'],
					array(
						'api_key' => $settings['authy.api.key']
					)
				);
				$result_body = json_decode($result['body'], true);

				if ($result_body['success']) {
					$this->Admin->create();
					$this->Admin->save(array('Admin' => array(
						'id' => $admin['Admin']['id'],
						'authy_uuid' => null,									
					)), false, array('authy_uuid'));	
					
					if ($this->Auth->login($admin)) {
						$this->Session->delete('authy-2-factor-authentication');
						$this->Session->setFlash('You have been successfully logged in.', 'flash_success');
						$this->redirect($this->Auth->redirect());
						return;
					}	
				}
				else {
					$this->Session->setFlash($result_body['message'], 'flash_error');
				}	
			}
			catch (Exception $e) {
				$this->Session->setFlash($e->getMessage(), 'flash_error');
			}		
		}
		$this->set(compact('admin_id'));
	}
	
	public function check_authy_request_status() {
		$admin_id = $this->Session->read('authy-2-factor-authentication');
		$setting_names = array(
			'authy.active', 
			'authy.api.key'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $setting_names,
				'Setting.deleted' => false
			)
		));
		if (count($settings) < count($setting_names)) {
			$this->Session->setFlash('Authy Settings not found.', 'flash_error');
			$link = '/admins/login';
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'denied',
					'redirect' => $link
				)),
				'type' => 'json',
				'status' => '201'
			));	
		}
		
		if ($this->request->is(array('put', 'post'))) {
			$admin = $this->Admin->findById($admin_id);
			try {
				$http = new HttpSocket(array(
					'timeout' => 30,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				
				$result = $http->get('https://api.authy.com/onetouch/json/approval_requests/'.$admin['Admin']['authy_uuid'],
					array(
						'api_key' => $settings['authy.api.key']
					)
				);
				$result_body = json_decode($result['body'], true);

				if ($result_body['success']) {
					if ($result_body['approval_request']['status'] == 'approved') {
						$this->Admin->create();
						$this->Admin->save(array('Admin' => array(
							'id' => $admin['Admin']['id'],
							'authy_uuid' => null,									
						)), false, array('authy_uuid'));	
						
						if ($this->Auth->login($admin)) {
							$this->Session->delete('authy-2-factor-authentication');
							$this->Session->setFlash('You have been successfully logged in.', 'flash_success');
							$link = $this->Auth->redirect();
							return new CakeResponse(array(
								'body' => json_encode(array(
									'status' => 'approved',
									'redirect' => $link
								)),
								'type' => 'json',
								'status' => '201'
							));	
						}		
					}
					if ($result_body['approval_request']['status'] == 'denied') {
						$this->Session->delete('authy-2-factor-authentication');
						$this->Session->setFlash('You declined rquest for login.', 'flash_success');
						$link = '/admins/login';
						return new CakeResponse(array(
							'body' => json_encode(array(
								'status' => 'denied',
								'redirect' => $link
							)),
							'type' => 'json',
							'status' => '201'
						));	
					}
				}	
				else {
					$this->Session->setFlash($result_body['message'], 'flash_error');
					$link = '/admins/login';
					return new CakeResponse(array(
						'body' => json_encode(array(
							'status' => 'denied',
							'redirect' => $link
						)),
						'type' => 'json',
						'status' => '201'
					));	
				}
			}
			catch (Exception $e) {
				$this->Session->setFlash($e->getMessage(), 'flash_error');
				$link = '/admins/login';
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => 'denied',
						'redirect' => $link
					)),
					'type' => 'json',
					'status' => '201'
				));	
			}
		}
		$this->render(false);
	}	
	
	public function authy_register() {
		$setting_names = array(
			'authy.active', 
			'authy.api.key'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $setting_names,
				'Setting.deleted' => false
			)
		));
		if (count($settings) < count($setting_names)) {
			$this->Session->setFlash('Authy Settings not found.', 'flash_error');
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'failed',
					'redirect' => '/admins/index',
				)),
				'type' => 'json',
				'status' => '201'
			));
		}

		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$admin = $this->Admin->findById($id);
			try {
				$http = new HttpSocket(array(
					'timeout' => 30,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));

				$result = $http->post('https://api.authy.com/protected/json/users/new',
					array(
						'api_key' => $settings['authy.api.key'],
						'user' => array(
							'email' => $admin['Admin']['admin_email'],
							'cellphone' => $admin['Admin']['phone_number'],
							'country_code' => $admin['Admin']['phone_country_code'],
						)
					)
				);
				$result_body = json_decode($result['body'], true);
				
				if ($result_body['success']) {
					$this->Admin->create();
					$this->Admin->save(array('Admin' => array(
						'id' => $admin['Admin']['id'],							
						'authy_user_id' => $result_body['user']['id'],						
					)), array(
						'validate' => false, 
						'callbacks' => false, 
						'fieldList' => array(
							'authy_user_id'
						)
					));
					return new CakeResponse(array(
						'body' => json_encode(array(
							'status' => 1
						)),
						'type' => 'json',
						'status' => '201'
					));
				}
				else {
					$this->Session->setFlash($result_body['message'], 'flash_error');
					return new CakeResponse(array(
						'body' => json_encode(array(
							'status' => 'failed',
							'redirect' => '/admins/index',
						)),
						'type' => 'json',
						'status' => '201'
					));	
				}
			}
			catch (Exception $e) {
				$this->Session->setFlash($e->getMessage(), 'flash_error');
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => 'failed',
						'redirect' => '/admins/index',
					)),
					'type' => 'json',
					'status' => '201'
				));
			}
		}
	}
}