<?php
App::uses('AppController', 'Controller');

class SettingsController extends AppController {
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function add() {
		if ($this->request->is('post')) {
			$this->Setting->create();
			$this->request->data['Setting']['user_id'] = $this->current_user['Admin']['id'];
			$save = $this->Setting->save($this->request->data); 
			if ($save) {
				$message = $this->current_user['Admin']['admin_user'].' has created setting '.$this->request->data['Setting']['name'].' with value "'.$this->request->data['Setting']['value'].'" ('.$this->request->data['Setting']['description'].')';
				$this->post_changes_on_slack($message);
				$this->Session->setFlash(__('Setting created.'), 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
	}
	
	public function index() {
		$this->Setting->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id'
			)
		))); 
		$settings = $this->Setting->find('all', array(
			'conditions' => array(
				'Setting.deleted' => false
			),
			'order' => 'Setting.name ASC'
		));
		$this->set(compact('settings'));
	}
	
	public function delete($id = null) {
		if (empty($id)) {
			throw new NotFoundException();
		}
		
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.id' => $id,
				'Setting.deleted' => false
			)
		));

		if (!$setting) {
			throw new NotFoundException();
		}
		
		$this->Setting->create();
		$this->Setting->save(array('Setting' => array(
			'id' => $setting['Setting']['id'],
			'deleted' => true,
			'user_id' => $this->current_user['Admin']['id']
		)), false, array('deleted', 'user_id'));
		$message = $this->current_user['Admin']['admin_user'].' has deleted setting '.$setting['Setting']['name'];
		$this->post_changes_on_slack($message);
		$this->Session->setFlash(__('Settings deleted successfully.'), 'flash_success');
		$this->redirect(array('action' => 'index'));
	}

	public function edit($id = null) {
		if (empty($id)) {
			throw new NotFoundException();
		}
		
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.id' => $id,
				'Setting.deleted' => false
			)
		));
		
		if (!$setting) {
			throw new NotFoundException();
		}
		
		if ($this->request->is(array('post', 'put'))) {
			$this->Setting->create();
			$this->Setting->save(array('Setting' => array(
				'id' => $setting['Setting']['id'],
				'deleted' => true
			)), true, array('deleted'));
			
			$this->request->data['Setting']['user_id'] = $this->current_user['Admin']['id'];
			$this->Setting->create();
			$this->Setting->save($this->request->data); 
			$message = $this->current_user['Admin']['admin_user'].' has updated setting '.$setting['Setting']['name']. ' from value "'.$setting['Setting']['value'].'" to "'.$this->request->data['Setting']['value'].'" ('.$this->request->data['Setting']['description'].')';
			$this->post_changes_on_slack($message);
			$this->Session->setFlash(__('Setting updated.'), 'flash_success');
			$this->redirect(array('action' => 'index'));
		}
		else {
			$this->request->data = $setting;
		}
	}
	
	public function history($setting_id = null) {
		if (empty($setting_id)) {
			throw new NotFoundException();
		}
		
		$setting = $this->Setting->find('first', array(
			'fields' => array('name'),
			'conditions' => array(
				'Setting.id' => $setting_id,
			)
		));

		$this->Setting->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id'
			)
		)));
		
		$settings = $this->Setting->find('all', array(
			'conditions' => array(
				'Setting.name' => $setting['Setting']['name'],
			),
			'order' => 'Setting.created DESC'
		));
		$this->set(compact('settings'));
	}
	
	private function post_changes_on_slack($message) {
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.settings_changes_alert.webhook',
				'Setting.deleted' => false
			)
		));
		if ($setting) {
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->post($setting['Setting']['value'], json_encode(array(
				'text' => $message,
				'username' => 'bernard'
			))); 		
		}
	}	
}