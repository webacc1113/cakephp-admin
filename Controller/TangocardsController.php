<?php
App::uses('AppController', 'Controller');

class TangocardsController extends AppController {
	public $components = array('Tango');
	public $uses = array('Tangocard', 'Setting');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function account() {
		$account = $this->Tango->account();
		if (!$account) {
			$this->Session->setFlash('We got an error accessing Tango account, please create an account from shell first or check logs.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$setting = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'name' => 'tango.cc',
				'Setting.deleted' => false
			)
		));
		if ($setting) {
			$this->set('active_date', json_decode($setting['tango.cc'])->active_date);
		}
		
		$this->set('account', $account);
	}
	
	public function add_cc() {
		if ($this->request->is('post')) {
			$credentials = $this->Tango->credentials();
			$account = $this->Tango->account('local');
			if (!$account || !$credentials) {
				$this->Session->setFlash('We got an error accessing Tango account or Tango settings.', 'flash_error');
				$this->redirect(array('action' => 'index'));
			}
			
			$request = array(
				'customer' => $account->customer,
				'account_identifier' => $account->identifier,
				'client_ip' => $_SERVER['REMOTE_ADDR'],
				'credit_card' => array(
					'number' => $this->request->data['Tango']['cc_number'],
					'security_code' => $this->request->data['Tango']['cvv'],
					'expiration' => $this->request->data['Tango']['expiration'],
					'billing_address' => array(
						'f_name' => $this->request->data['Tango']['first_name'],
						'l_name' => $this->request->data['Tango']['last_name'],
						'address' => $this->request->data['Tango']['address'],
						'city' => $this->request->data['Tango']['city'],
						'state' => $this->request->data['Tango']['state'],
						'zip' => $this->request->data['Tango']['zip'],
						'country' => $this->request->data['Tango']['country'],
						'email' => $this->request->data['Tango']['email'],
					)
				)
			);
			
			$HttpSocket = new HttpSocket(array(
				'timeout' => 5,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
			$error = '';
			try {
				$response = $HttpSocket->post($credentials['tango.api_host'] . 'cc_register', json_encode($request));
				CakeLog::write('tango.account', 'cc_register : ' . print_r($response, true));
			} catch (Exception $e) {
				$error = 'Api call failed, please try again';
			}
			
			
			$response = json_decode($response);
			if (!$response->success) {
				if (isset($response->invalid_inputs_message)) {
					$error = $response->invalid_inputs_message;
				}
				elseif (isset($response->denial_message)) {
					$error = $response->denial_message;
				}
			}
			
			if ($response->success && isset($response->cc_token) && $response->cc_token) {
				
				// save cvv too, we need this for autofund
				$response->cvv = $this->request->data['Tango']['cvv'];
				if ($this->Tango->save_cc($response)) {
					$this->Session->setFlash('Your credit card has been registered successfully!', 'flash_success');
				}
				else {
					$this->Session->setFlash('Your credit card has been registered, however we got an issue, while unregistering the old card, check logs please.', 'flash_success');
				}
				
				$this->redirect(array('action' => 'account'));
			}
			else {
				$this->Session->setFlash('Credit card not saved<br />'.$error, 'flash_error');
			}
		}
	}
	
	public function fund_cc() {
		$setting = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'name' => 'tango.cc',
				'Setting.deleted' => false
			)
		));
		
		if (!$setting) {
			$this->Session->setFlash('Credit card not registered. Please register a credit card first!', 'flash_error');
			$this->redirect('add_cc');
		}
		
		if ($this->request->is('post')) {
			$credentials = $this->Tango->credentials();
			$account = $this->Tango->account('local');
			if (!$account || !$credentials) {
				$this->Session->setFlash('We got an error accessing Tango account or Tango settings.', 'flash_error');
			}
			
			$request = array(
				'customer' => $account->customer,
				'account_identifier' => $account->identifier,
				'client_ip' => $_SERVER['REMOTE_ADDR'],
				'amount' => $this->request->data['Tango']['amount'] * 100,
				'cc_token' => json_decode($setting['tango.cc'])->cc_token,
				'security_code' => $this->request->data['Tango']['cvv']
			);
			
			$HttpSocket = new HttpSocket(array(
				'timeout' => 5,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
			$error = '';
			try {
				$response = $HttpSocket->post($credentials['tango.api_host'] . 'cc_fund', json_encode($request));
				CakeLog::write('tango.fund', print_r($response, true));
			} catch (Exception $e) {
				$error = 'Api call failed, please try again';
			}
			
			$response = json_decode($response);
			if (!$response->success) {
				if (isset($response->invalid_inputs_message)) {
					$error = $response->invalid_inputs_message;
				}
				elseif (isset($response->denial_message)) {
					$error = $response->denial_message;
				}
				
			}

			if ($error) {
				$this->Session->setFlash('Credit card not funded.<br />' . $error, 'flash_error');
			}
			else {
				$this->Session->setFlash('Credit card funded successfully!', 'flash_success');
				$this->redirect(array('action' => 'account'));
			}
		}
	}
	
	public function index() {
		$this->paginate = array(
			'Tangocard' => array(
				'limit' => 10,
				'conditions' => array(
					'Tangocard.parent_id is null',
					'Tangocard.deleted' => false
				),
				'order' => 'Tangocard.name ASC'
			)
		);
		$this->set('cards', $this->paginate());
	}
	
	public function import_card() {
		if ($this->request->is('post') || $this->request->is('ajax')) {
			$errors = array();
			if ($this->request->data['Tangocard']['name'] == '') {
				$errors[] = __('Please enter the card name.');
			}
			
			$brand = json_decode($this->request->data['Tangocard']['brand']);
			if ($brand->rewards[0]->is_variable == '1') {
				$skus[] = $brand->rewards[0]->sku;
			}
			else {
				foreach ($brand->rewards as $reward) {
					$skus[] = $reward->sku;
				}
			}

			$count = $this->Tangocard->find('count', array(
				'conditions' => array(
					'Tangocard.sku' => $skus,
					'Tangocard.deleted' => false
				)
			));
			
			if ($count) {
				$errors[] = 'One or more of the following skus alreay exist. <br />'.implode('<br />', $skus);
			}
			
			if (empty($errors)) {
				$tangocard = array('Tangocard' => array(
					'name' => $this->request->data['Tangocard']['name'],
					'transaction_name' => $this->request->data['Tangocard']['transaction_name'],
					'type' => $this->request->data['Tangocard']['type'],
					'logo' => $brand->image_url,
					'description' => $this->request->data['Tangocard']['description'],
					'long_description' => $this->request->data['Tangocard']['long_description'],
					'disclaimer' => $this->request->data['Tangocard']['disclaimer'],
					'redemption_instructions' => $this->request->data['Tangocard']['redemption_instructions'],
					'allowed_us' => $this->request->data['Tangocard']['allowed_us'],
					'allowed_ca' => $this->request->data['Tangocard']['allowed_ca'],
					'allowed_gb' => $this->request->data['Tangocard']['allowed_gb'],
					'conversion' => $this->request->data['Tangocard']['conversion'],
					'currency' => $brand->rewards[0]->currency_code
				));
				if ($brand->rewards[0]->is_variable == '1') {
					$tangocard['Tangocard']['sku'] = $brand->rewards[0]->sku;
					$tangocard['Tangocard']['min_value'] = $brand->rewards[0]->min_price;
					$tangocard['Tangocard']['max_value'] = $brand->rewards[0]->max_price;
					
				}
				
				$tangocardSource = $this->Tangocard->getDataSource();
				$tangocardSource->begin();
				$this->Tangocard->create();
				if (!$this->Tangocard->save($tangocard)) {
					$tangocardSource->commit();
					$validationErrors = $this->Tangocard->validationErrors;
					if (isset($validationErrors['name'])) {
						$errors[] = $validationErrors['name'][0];
					}

					if (isset($validationErrors['sku'])) {
						$errors[] = $validationErrors['sku'][0];
					}

					if (isset($validationErrors['min_value'])) {
						$errors[] = $validationErrors['min_value'][0];
					}

					if (isset($validationErrors['max_value'])) {
						$errors[] = $validationErrors['max_value'][0];
					}
					
					if (isset($validationErrors['currency'])) {
						$errors[] = $validationErrors['currency'][0];
					}
					
					if (isset($validationErrors['conversion'])) {
						$errors[] = $validationErrors['conversion'][0];
					}
				}
				elseif ($brand->rewards[0]->is_variable != '1') { // in this case the parent has already been saved.
					$tangocard_id = $this->Tangocard->getLastInsertID();
					$tangocardSource->commit();
					foreach ($brand->rewards as $reward) {
						$this->Tangocard->create();
						$save = $this->Tangocard->save(array('Tangocard' => array(
							'parent_id' => $tangocard_id,
							'sku' => $reward->sku,
							'name' => $reward->description,
							'transaction_name' => $this->request->data['Tangocard']['transaction_name'],
							'value' => $reward->denomination,
							'currency' => $reward->currency_code,
						)));
						if (!$save) {
							$errors[] = 'Child card not saved, check log please!';
							CakeLog::write('tango.rewards', print_r($this->Tangocard->validationErrors, true));
						}
					}
				}
				else {
					$tangocardSource->commit();
				}
			}
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'errors' => implode(' ', $errors)
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
		
		$credentials = $this->Tango->credentials();
		if (!$credentials) {
			$this->Session->setFlash('TangoCards credentials not found.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		try {
			$response = $HttpSocket->get($credentials['tango.api_host'] . 'rewards');
		} catch (Exception $e) {
			$this->Session->setFlash('Api call failed, please try again.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$response = json_decode($response);
		//CakeLog::write('tangocards', print_r($response, true));
		if (!$response->success) {
			$this->Session->setFlash('Api call failed to retrieve gift cards.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$this->set('brands', $response->brands);
	}
	
	public function edit($id) {
		$tangocard = $this->Tangocard->findById($id);
		if (!$tangocard) {
			throw new NotFoundException(__('Invalid card'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->request->data['Tangocard']['id'] = $id;
			$this->request->data['Tangocard']['segment_transaction_name'] = Inflector::slug($this->request->data['Tangocard']['segment_transaction_name'], ' ');
			if ($this->Tangocard->save($this->request->data)) {
				if (!empty($this->request->data['Tangocard']['transaction_name']) && !empty($tangocard['Children'])) {
					
					// Update transaction title for children if empty
					foreach ($tangocard['Children'] as $child) {
						if (empty($child['transaction_name'])) {
							$this->Tangocard->create();
							$this->Tangocard->save(array('Tangocard' => array(
								'id' => $child['id'],
								'transaction_name' => $this->request->data['Tangocard']['transaction_name']
							)), true, array('transaction_name'));
						}
					}
				}
				
				$this->Session->setFlash(__('Card has been updated.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
			
			$this->Session->setFlash(__('Unable to update the card.'), 'flash_error');
		}
		else {
			$this->request->data = $tangocard;
		}
	}

	public function delete($id) {
		$tangocard = $this->Tangocard->find('first', array(
			'contain' => array(
				'Children' => array(
					'fields' => array('Children.id')
				),
				'Parent' => array(
					'fields' => array('Parent.id'),
					'Children' => array(
						'fields' => array('Children.id')
					)
				)
			),
			'conditions' => array(
				'Tangocard.id' => $id
			)
		));
		
		if (!$tangocard) {
			throw new NotFoundException(__('Invalid card'));
		}
		
		if (!empty($tangocard['Children'])) { // Delete children too, if a parent is deleted.
			foreach ($tangocard['Children'] as $child) {
				$this->Tangocard->create();
				$this->Tangocard->save(array('Tangocard' => array(
					'id' => $child['id'],
					'deleted' => true
				)), true, array('deleted'));
			}
		}
		elseif (!empty($tangocard['Tangocard']['parent_id']) && count($tangocard['Parent']['Children']) == 1) { // Delete the parent too, if its the last child getting deleted.
			$this->Tangocard->create();
			$this->Tangocard->save(array('Tangocard' => array(
				'id' => $tangocard['Parent']['id'],
				'deleted' => true
			)), true, array('deleted'));
		}
		
		$this->Tangocard->create();
		$this->Tangocard->save(array('Tangocard' => array(
			'id' => $id,
			'deleted' => true
		)), true, array('deleted'));
		$this->Session->setFlash(__('Tangocard deleted.'), 'flash_success');
		$this->redirect(array('action' => 'index'));
	}
	
	public function orders() {
		$credentials = $this->Tango->credentials();
		$account = $this->Tango->account('local');
		if (!$account || !$credentials) {
			$this->Session->setFlash('Tango account or Tango settings not found.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$limit = 100;
		$page = (isset($this->request->query['page'])) ? $this->request->query['page'] : 1; 
		$offset = ($page-1) * $limit; 

		$request = array(
			'customer' => $account->customer,
			'account_identifier' => $account->identifier,
			'offset' => $offset,
			'limit' => $limit
		);
		if (isset($this->request->query['date_from']) && !empty($this->request->query['date_from'])) {
			$request['start_date'] = date("c", strtotime($this->request->query['date_from'].' 00:00:00')); 
		}
		
		if (isset($this->request->query['date_to']) && !empty($this->request->query['date_to'])) {
			$request['end_date'] = date("c", strtotime($this->request->query['date_to'].' 23:59:59'));
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		try {
			$response = $HttpSocket->get($credentials['tango.api_host'] . 'orders', $request);
		} 
		catch (Exception $e) {
			$this->Session->setFlash('Api call failed, please try again.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$response = json_decode($response, true);
		if (!$response['success']) {
			$this->Session->setFlash('Api call failed to retrieve gift cards.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$total_pages = ceil($response['total_count'] / $limit);
		$this->set('orders', $response);
		$this->set('total_pages', $total_pages);
	}
	
	public function resend_reward($tangocard_order_id) {
		$credentials = $this->Tango->credentials();
		if (!$credentials) {
			$this->Session->setFlash('We got an error accessing Tango account or Tango settings.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		try {
			$response = $HttpSocket->post($credentials['tango.api_host'] . 'orders/'.$tangocard_order_id.'/resend');
			CakeLog::write('tango.resend', print_r($e, true));
		} 
		catch (Exception $e) {
			CakeLog::write('tango.resend', print_r($e, true));
			$this->Session->setFlash('Api call failed, please try again.', 'flash_error');
			$this->redirect($this->referer());
		}
		
		$response = json_decode($response, true);
		if ($response['success']) {
			$this->loadModel('TangocardOrder');
			$tangocard_order = $this->TangocardOrder->find('first', array(
				'conditions' => array(
					'TangocardOrder.order_id' => $tangocard_order_id
				)
			));
			if ($tangocard_order) {
				$this->TangocardOrder->create();
				$this->TangocardOrder->save(array('TangocardOrder' => array(
					'id' => $tangocard_order['TangocardOrder']['id'],
					'resend_count' => $tangocard_order['TangocardOrder']['resend_count'] + 1,
					'last_resend' => date(DB_DATETIME)
				)), true, array('resend_count', 'last_resend'));
			}
			
			$this->Session->setFlash('Reward email has been resent.', 'flash_success');
		}
		else {
			$this->Session->setFlash('Reward email not sent.<br />Error: '. $response['error_message'], 'flash_error');
		}
		
		$this->redirect($this->referer());
	}
	
	public function ajax_resend_info($order_id) {
		$this->loadModel('TangocardOrder');
		$tangocard_order = $this->TangocardOrder->find('first', array(
			'conditions' => array(
				'order_id' => $order_id
			)
		));
		if ($tangocard_order) {
			$this->set(compact('tangocard_order'));
		}
		$this->layout = false;
	}
	
	public function ajax_amount($sku) {
		$this->layout = false;
		$tangocard = $this->Tangocard->find('first', array(
			'conditions' => array(
				'Tangocard.sku' => $sku
			)
		));
		if (!$tangocard) {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => 'Tangocard not found'
				)), 
				'type' => 'json',
				'status' => '400'
			));
		}
		
		return new CakeResponse(array(
			'body' => json_encode(array(
				'amount' => $tangocard['Tangocard']['value']
			)), 
			'type' => 'json',
			'status' => '201'
		));
		
		
	}

}
