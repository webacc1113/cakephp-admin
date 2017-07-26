<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');
class ApiUsersController extends AppController {
	public $uses = array('ApiUser', 'Admin', 'Group', 'Client');
	public $components = array('RequestHandler');

	public function index() {
		$this->ApiUser->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'fields' => array('id', 'admin_user'),
				'className' => 'Admin',
				'foreignKey' => 'admin_user_id'
			),
			'Group' => array(
				'fields' => array('id', 'name'),
				'className' => 'Group',
				'foreignKey' => 'group_id'
			),
			'Client' => array(
				'fields' => array('id', 'client_name'),
				'className' => 'Client',
				'foreignKey' => 'client_id'
			)
		)));
		$paginate = array(
			'conditions' => array(
				'ApiUser.type' => 'partner'
			),
			'limit' => 50,
			'order' => 'ApiUser.modified DESC'
		);
		$this->paginate = $paginate;
		$api_users = $this->paginate('ApiUser');
		$groups = $this->Group->find('list', array(
			'order' => 'Group.name ASC'
		));
		$this->set(compact('api_users', 'groups'));
	}

	public function add() {
		if ($this->request->is('post')) {
			$this->request->data['ApiUser']['type'] = 'partner'; 
			$this->request->data['ApiUser']['testmode_user_ids'] = trim($this->request->data['ApiUser']['testmode_user_ids']);
			$this->ApiUser->create();
			if ($this->ApiUser->save($this->request->data)) {
				$this->Session->setFlash(__('Api User has been saved.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
		}
		$admins = $this->Admin->find('list', array(
			'fields' => array('Admin.id', 'Admin.admin_user')
		));

		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'order' => 'Group.name ASC'
		));
		
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$clients = $this->Client->find('list', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Client.group_id' => $mintvine_group['Group']['id'],
				'Client.deleted' => false
			), 
			'order' => 'Client.client_name ASC'
		));	
		$mintvine_client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.key' => 'mintvine',
			)
		));

		$this->set(compact('admins', 'groups', 'clients', 'mintvine_client'));
	}

	public function edit($id) {
		$api_user = $this->ApiUser->findById($id);
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->request->data['ApiUser']['testmode_user_ids'] = trim($this->request->data['ApiUser']['testmode_user_ids']);
			if (!$this->request->data['ApiUser']['password']) {
				unset($this->request->data['ApiUser']['password']);
			}
			if ($this->ApiUser->save($this->request->data)) {
				$this->Session->setFlash(__('Api User has been updated.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
		}
		$admins = $this->Admin->find('list', array(
			'fields' => array('Admin.id', 'Admin.admin_user')
		));
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'order' => 'Group.name ASC'
		));
		
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$clients = $this->Client->find('list', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Client.group_id' => $mintvine_group['Group']['id'],
				'Client.deleted' => false
			), 
			'order' => 'Client.client_name ASC'
		));	
		if (!$this->request->data) {
        	$this->request->data = $api_user;
    	}
		$this->set(compact('api_user', 'admins', 'groups', 'clients'));
	}

	public function active() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$api_user = $this->ApiUser->findById($id);
			$active = ($api_user['ApiUser']['active']) ? 0 : 1;
			$this->ApiUser->save(array('ApiUser' => array(
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
	
	public function ajax_toggle_mode() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$api_user = $this->ApiUser->findById($id);
			$livemode = ($api_user['ApiUser']['livemode']) ? 0 : 1;
			$this->ApiUser->save(array('ApiUser' => array(
				'id' => $id,
				'livemode' => $livemode,
			)), true, array('livemode'));

			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $livemode
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}

	public function delete() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$api_user = $this->ApiUser->findById($id);
			$this->ApiUser->delete($id);
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}

	public function ajax_create_admin() {
		if ($this->request->is('Post')) {
			$authed_user = $this->Auth->user();
			$this->request->data['group_id'] = $authed_user['Admin']['group_id'];

			$this->Admin->create();
			$this->Admin->set(array('Admin' => $this->request->data));
			if (!$this->Admin->validates()) {
				$error_messages = array();
				foreach ($this->Admin->validationErrors as $field => $validationErrors) {
					$error_messages[$field] = $validationErrors[0];
				}
				return new CakeResponse(array(
					'status' => 400,
					'type' => 'json',
					'body' => json_encode($error_messages)
				));
			}
			$this->Admin->getDataSource()->begin();
			$this->Admin->create();
			$this->Admin->save(array('Admin' => $this->request->data));
			$admin_id = $this->Admin->getInsertId();
			$this->Admin->getDataSource()->commit();

			$admin = $this->Admin->find('first', array(
				'conditions' => array(
					'id' => $admin_id
				)
			));
			$return = array($admin_id => $admin['Admin']['admin_user']);
			return new CakeResponse(array(
				'status' => 201,
				'type' => 'json',
				'body' => json_encode($return)
			));
		}
	}
}