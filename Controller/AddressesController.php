<?php

App::uses('AppController', 'Controller');

class AddressesController extends AppController {

	public $uses = array('UserAddress', 'GeoState', 'LucidZip');

	public function beforeFilter() {
		parent::beforeFilter();
	}

	public function index($user_id = null) {
		if (!$user_id) {
			throw new NotFoundException();
		}
		App::import('Model', 'QueryProfile');
		$this->QueryProfile = new QueryProfile;
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'fields' => array(
				'QueryProfile.country',
			)
		));
		if (!empty($user)) {
			return $this->redirect(array('action' => strtolower($user['QueryProfile']['country']), $user_id)); 
		}
	}
	
	public function ca($user_id = null) {
		if (!$user_id) {
			throw new NotFoundException();
		}
		App::import('Model', 'QueryProfile');
		$this->QueryProfile = new QueryProfile;
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'fields' => array(
				'User.id',
				'User.email',
				'QueryProfile.postal_code',
				'QueryProfile.country',
			)
		));
		$user_address = $this->UserAddress->find('first', array(
			'conditions' => array(
				'UserAddress.user_id' => $user_id,
				'UserAddress.deleted' => false
			), 
			'order' => 'UserAddress.id DESC'
		));		

		if (!$user_address) {
			$user_address = array('UserAddress' => array(
				'postal_code' => $user['QueryProfile']['postal_code']
			)); 
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			
			$this->request->data['UserAddress']['user_id'] = $this->request->data['User']['id'];
			$this->request->data['UserAddress']['country'] = $user['QueryProfile']['country'];
			$this->UserAddress->create();
			$save = $this->UserAddress->save($this->request->data);
						
			$query_profile = $this->QueryProfile->find('first', array(
				'fields' => array('QueryProfile.id'),
				'conditions' => array(
					'QueryProfile.user_id' => $user_id
				),
				'recursive' => -1
			));
			// update query profile with values
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'postal_code' => $this->data['UserAddress']['postal_code']
			)), true, array('postal_code'));
			
			$this->Session->setFlash(__('User\'s address updated.'), 'flash_success');
			$this->redirect(array(
				'controller' => 'users',
				'action' => 'history',
				$user_id
			));
		}
		else {
			$this->data = $user_address;
			$this->request->data['User'] = $user['User'];
		}
	}
	
	public function gb($user_id = null) {
		if (!$user_id) {
			throw new NotFoundException();
		}
		App::import('Model', 'QueryProfile');
		$this->QueryProfile = new QueryProfile;
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'fields' => array(
				'User.id',
				'User.email',
				'QueryProfile.postal_code',
				'QueryProfile.country',
			)
		));
		$user_address = $this->UserAddress->find('first', array(
			'conditions' => array(
				'UserAddress.user_id' => $user_id,
				'UserAddress.deleted' => false
			), 
			'order' => 'UserAddress.id DESC'
		));		

		if (!$user_address) {
			$user_address = array('UserAddress' => array(
				'postal_code' => $user['QueryProfile']['postal_code']
			)); 
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			
			$this->request->data['UserAddress']['user_id'] = $this->request->data['User']['id'];
			$this->request->data['UserAddress']['country'] = $user['QueryProfile']['country'];
			$this->UserAddress->create();
			$save = $this->UserAddress->save($this->request->data);
						
			$query_profile = $this->QueryProfile->find('first', array(
				'fields' => array('QueryProfile.id'),
				'conditions' => array(
					'QueryProfile.user_id' => $user_id
				),
				'recursive' => -1
			));
			// update query profile with values
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'postal_code' => $this->data['UserAddress']['postal_code']
			)), true, array('postal_code'));
			
			$this->Session->setFlash(__('User\'s address updated.'), 'flash_success');
			$this->redirect(array(
				'controller' => 'users',
				'action' => 'history',
				$user_id
			));
		}
		else {
			$this->data = $user_address;
			$this->request->data['User'] = $user['User'];
		}
	}
	
	public function us($user_id = null) {
		if (!$user_id) {
			throw new NotFoundException();
		}
		App::import('Model', 'QueryProfile');
		$this->QueryProfile = new QueryProfile;
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id
			),
			'fields' => array(
				'User.id',
				'User.email',
				'QueryProfile.postal_code',
				'QueryProfile.country',
			)
		));
		$postal_code = substr($user['QueryProfile']['postal_code'], 0, 5);
		$user_address = $this->UserAddress->find('first', array(
			'conditions' => array(
				'UserAddress.user_id' => $user_id,
				'UserAddress.deleted' => false
			),
			'order' => 'UserAddress.id DESC'
		));

		$lucid_zip = $this->LucidZip->find('first', array(
			'conditions' => array(
				'LucidZip.zipcode' => $postal_code
			)
		));
		if ($lucid_zip) {
			$user_address['UserAddress']['state'] = $lucid_zip['LucidZip']['state_abbr'];
		}
		$states = $this->GeoState->find('list', array(
			'fields' => array('GeoState.state_abbr', 'GeoState.state'),
			'conditions' => array(
				'GeoState.country_code' => 'US',
				'GeoState.id >' => '0' // ignore the N/A
			),
			'order' => 'GeoState.state_abbr ASC'
		));
		
		$counties = array();
		if ($lucid_zip && !empty($lucid_zip['LucidZip']['state_abbr'])) {
			$lucid_counties = $this->LucidZip->find('all', array(
				'fields' => array('LucidZip.county_fips', 'LucidZip.state_fips', 'LucidZip.county'),
				'conditions' => array(
					'LucidZip.state_abbr' => $lucid_zip['LucidZip']['state_abbr'],
					'LucidZip.county != ""',
				),
				'order' => 'LucidZip.county ASC'
			));
			
			if (!empty($lucid_counties)) {
				foreach ($lucid_counties as $county) {
					if (empty($county['LucidZip']['state_fips'])) {
						continue;
					}
					
					$formatted_county = str_pad($county['LucidZip']['state_fips'], 2, '0', STR_PAD_LEFT).str_pad($county['LucidZip']['county_fips'], 3, '0', STR_PAD_LEFT);
					$counties[$formatted_county] = ucwords(strtolower($county['LucidZip']['county']));
				}
			}
		}
		
		if ($this->request->is(array('POST', 'PUT'))) {
			
			$this->request->data['UserAddress']['user_id'] = $this->request->data['User']['id'];
			$this->request->data['UserAddress']['country'] = $user['QueryProfile']['country'];
			$this->request->data['UserAddress']['county_fips'] = $this->request->data['UserAddress']['county'];
			$this->request->data['UserAddress']['county'] = $counties[$this->request->data['UserAddress']['county']];
			
			$this->UserAddress->create();
			$save = $this->UserAddress->save(array('UserAddress' => $this->request->data['UserAddress']));

			$query_profile = $this->QueryProfile->find('first', array(
				'fields' => array('QueryProfile.id'),
				'conditions' => array(
					'QueryProfile.user_id' => $this->request->data['User']['id']
				),
				'recursive' => -1
			));
			
			// update query profile with values
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'state' => $this->data['UserAddress']['state'],
				'postal_code' => $this->data['UserAddress']['postal_code'],
				'postal_code_extended' => $this->data['UserAddress']['postal_code_extended'],
				'dma_code' => $lucid_zip ? $lucid_zip['LucidZip']['dma'] : null
			)), true, array('state', 'postal_code', 'postal_code_extended', 'dma_code'));
			
			$this->Session->setFlash(__('User\'s address updated.'), 'flash_success');
			$this->redirect(array(
				'controller' => 'users',
				'action' => 'history',
				$user_id
			));
		}
		else {
			$this->data = $user_address;			
			$this->request->data['User'] = $user['User'];
		}
		$this->set(compact('countries', 'states', 'counties'));
	}
	
	public function precision() {
		if ($this->request->is('post')) {
			if ($this->request->data['UserAddress']['file']['size'] < 1) {
				$this->Session->setFlash(__('Please select a valid CSV file'), 'flash_error');
				$this->redirect(array('action' => 'precision'));
			}
			
			$errors = array();
			$csv_rows = Utils::csv_to_array($this->request->data['UserAddress']['file']['tmp_name']); 
			$csv_rows = $this->clean_precision_data($csv_rows);
			if (!$csv_rows) {
				$this->Session->setFlash(__('The file is either empty or missing any of the following columns.<br />External Memberid, First Name, Last Name, Street Address, City, Country Name, State Name, Zip Code'), 'flash_error');
				$this->redirect(array('action' => 'precision'));
			}
			
			foreach ($csv_rows as $row) {
				$user_record = $this->UserAddress->find('first', array(
					'conditions' => array(
						'user_id' => $row[$this->precision_indexes['user_id']]
					)
				));
				if ($user_record) {
					continue;
				}
				
				$country = $state = '';
				$postal_code = $row[$this->precision_indexes['postal_code']];
				if ($row[$this->precision_indexes['country']] == 'UNITED STATES OF AMERICA') {
					$country = 'US';
					$geo_state = $this->GeoState->find('first', array(
						'fields' => array('GeoState.state_abbr', 'GeoState.state'),
						'conditions' => array(
							'GeoState.country_code' => 'US',
							'GeoState.state' => $row[$this->precision_indexes['state']],
						)
					));
					if ($geo_state) {
						$state = $geo_state['GeoState']['state_abbr'];
					}
					
					$postal_code = sprintf("%05d", $postal_code);
				}
				elseif ($row[$this->precision_indexes['country']] == 'CANADA') {
					$country = 'CA';
				}
				elseif ($row[$this->precision_indexes['country']] == 'UNITED KINGDOM') {
					$country = 'GB';
				}
				else {

					// we don't support any other country
					continue;
				}

				$this->UserAddress->create();
				if (!$this->UserAddress->save(array('UserAddress' => array(
					'user_id' => $row[$this->precision_indexes['user_id']],
					'first_name' => $row[$this->precision_indexes['first_name']],
					'last_name' => $row[$this->precision_indexes['last_name']],
					'country' => $country,
					'postal_code' => $postal_code,
					'state' => $state,
					'city' => $row[$this->precision_indexes['city']],
					'address_line1' => $row[$this->precision_indexes['street_address']],
				)))) {
					$errors[] = __('User #'.$row[$this->precision_indexes['user_id']].' address failed to save. <br />Reason: '.  print_r($this->UserAddress->validationErrors, true), true);
				}
			}
			
			if ($errors) {
				$this->set(compact('errors'));
			}
			else {
				$this->Session->setFlash(__('User Addresses data has been imported successfully!'), 'flash_success');
			}
		}
	}
	
	private function clean_precision_data($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['user_id'] = array_search('External Memberid', $header);
		$indexes['first_name'] = array_search('First Name', $header);
		$indexes['last_name'] = array_search('Last Name', $header);
		$indexes['street_address'] = array_search('Street Address', $header);
		$indexes['city'] = array_search('City', $header);
		$indexes['country'] = array_search('Country Name', $header);
		$indexes['state'] = array_search('State Name', $header);
		$indexes['postal_code'] = array_search('Zip Code', $header);
		$this->precision_indexes = $indexes;
		foreach ($indexes as $val) {
			if ($val === false) {
				return false;
			}
		}
		
		foreach ($data as $key => $val) {
			$has_values = false;
			foreach ($val as $k => $v) {
				if (!empty($v)) {
					$has_values = true;
					break;
				}
			}
			if (!$has_values) {
				unset($data[$key]); 
			}
		}
		return $data;
	}

}

?>
