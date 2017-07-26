<?php
App::uses('AppController', 'Controller');

class ClientsController extends AppController {
	public $uses = array('Client', 'GeoCountry', 'GeoState');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('QuickBook');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$mintvine_group = $this->Client->Group->find('first', array(
			'fields' => array('Group.id', 'Group.name'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		)); 
		
		$conditions = array('Client.deleted' => false);		
		if (isset($this->request->query['status']) && $this->request->query['status'] == 'hidden') {
			$conditions[] = array('Client.hide_from_reports' => true);
		}
		$groups = $this->Admin->groups($this->current_user);
		if (isset($this->request->query['group_id']) && $this->request->query['group_id'] && isset($groups[$this->request->query['group_id']])) {
			$conditions['Client.group_id'] = $this->request->query['group_id'];
		}
		else {
			$conditions['Client.group_id'] = array_keys($groups);
		}
		$this->set(compact('mintvine_group'));
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$paginate = array(
			'Client' => array(
				'limit' => 600,
				'order' => 'Client.client_name ASC',
			)
		);
		
		if (!empty($conditions)) {
			$paginate['Client']['conditions'] = $conditions;
		}
		
		$this->paginate = $paginate;
		$this->set('clients', $this->paginate());
		$this->set('groups', $groups);
	}
	
	public function add() {
		$groups = $this->Admin->groups($this->current_user);
		if ($this->request->is('post')) {
			
			// permission check
			if (!isset($groups[$this->request->data['Client']['group_id']])) {
				$this->Session->setFlash(__('You are not authorized to access this group.'), 'flash_error');
				return $this->redirect(array('action' => 'index'));
			}
			
			$this->Client->create();
            if ($this->Client->save($this->request->data)) {
                $this->Session->setFlash(__('Client has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
			
            $this->Session->setFlash(__('Unable to add the client.'), 'flash_error');
        }
		
		$mintvine_group = $this->Client->Group->find('first', array(
			'fields' => array('Group.id', 'Group.name'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		)); 
		
		$geo_countries = $this->GeoCountry->find('list', array(
			'fields' => array(
				'id', 'country'
			),
			'order' => array(
				'country' => 'ASC'
			)
		));
		
		$geo_states = $this->GeoState->find('list', array(
			'fields' => array(
				'id', 'state'
			),
			'order' => array(
				'state' => 'ASC'
			)
		));
		
		$this->set(compact('geo_countries', 'geo_states', 'mintvine_group', 'groups'));
	}
	
	public function edit($client_id) {
		$client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.id' => $client_id
			)
		));
		if (!$client) {
        	throw new NotFoundException(__('Invalid client'));
    	}
		$groups = $this->Admin->groups($this->current_user);
    	if ($this->request->is('post') || $this->request->is('put')) {
			
			// permission check
			if (!isset($groups[$this->request->data['Client']['group_id']])) {
				$this->Session->setFlash(__('You are not authorized to access this group.'), 'flash_error');
				return $this->redirect(array('action' => 'index'));
			}
			
			$this->Client->create();
			$save = $this->Client->save($this->request->data, true, array(
				'client_name',
				'code_name',
				'address_line1',
				'address_line2',
				'geo_country_id',
				'city',
				'geo_state_id',
				'postal_code',
				'billing_name',
				'billing_email',
				'net',
				'project_name',
				'project_email',
				'quickbook_customer_id',
				'do_not_autolaunch',
				'group_id',
				'param_type',
				'notes'
			));
        	if ($save) {
				$this->QuickBook->update_quickbook_customer($client_id);
        	    $this->Session->setFlash(__('Client has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index', '?' => array('group_id' => $client['Client']['group_id'])));
        	}
			
        	$this->Session->setFlash(__('Unable to update the client.'), 'flash_error');
    	}
		
    	if (!$this->request->data) {
        	$this->request->data = $client;
    	}
		
		$geo_countries = $this->GeoCountry->find('list', array(
			'fields' => array('GeoCountry.id', 'GeoCountry.country'),
			'order' => array('GeoCountry.country ASC')
		));
		
		$geo_states = $this->GeoState->find('list', array(
			'fields' => array('GeoState.id', 'GeoState.state'),
			'order' => 'GeoState.state ASC'
		));
		
		$this->set(compact('geo_countries', 'geo_states', 'groups', 'client'));
	}
	
	public function delete() {
    	if ($this->request->is('post') || $this->request->is('put')) {
    		$id = $this->request->data['id'];
			$client = $this->Client->findById($id);
			$groups = $this->Admin->groups($this->current_user);
			
			// permission check
			if (!isset($groups[$client['Client']['group_id']])) {
				return new CakeResponse(array('status' => '401'));
			}
			
			$client = $this->Client->find('first', array(
				'conditions' => array(
					'Client.id' => $id,
					'Client.deleted' => false
				),
				'fields' => array('id'),
				'recursive' => -1
			));
			
			if (!$client) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '0'
					)),
					'type' => 'json',
					'status' => '201'
				));
			}
			
			$this->Client->create();
			$this->Client->save(array('Client' => array(
				'id' => $client['Client']['id'],
				'deleted' => true,
			)), true, array('deleted'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)),
				'type' => 'json',
				'status' => '201'
			));
    	}
	}
	
	public function ajax_key($client_id) {
		$client = $this->Client->findById($client_id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'key' => $client && !empty($client['Client']['key']) ? $client['Client']['key']: ''
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function hide() {
		// hide/un-hide from reports
    	if ($this->request->is('post') || $this->request->is('put')) {
    		$id = $this->request->data['id'];
			$client = $this->Client->findById($id);
			$groups = $this->Admin->groups($this->current_user);
			
			// permission check
			if (!isset($groups[$client['Client']['group_id']])) {
				return new CakeResponse(array('status' => '401'));
			}
			
			$client = $this->Client->find('first', array(
				'conditions' => array(
					'Client.id' => $id,
					'Client.deleted' => false
				),
				'fields' => array('id', 'hide_from_reports'),
				'recursive' => -1
			));
			
			if (!$client) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '0'
					)),
					'type' => 'json',
					'status' => '201'
				));
			}
			
			$this->Client->create();
			$this->Client->save(array('Client' => array(
				'id' => $client['Client']['id'],
				'hide_from_reports' => !$client['Client']['hide_from_reports'],
			)), true, array('hide_from_reports'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'hide_status' => !$client['Client']['hide_from_reports']
				)),
				'type' => 'json',
				'status' => '201'
			));
    	}
	}
}