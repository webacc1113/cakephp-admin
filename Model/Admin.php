<?php
App::uses('AppModel', 'Model');
App::uses('AuthComponent', 'Controller/Component');

class Admin extends AppModel {
	public $actsAs = array('Containable');
	public $displayField = 'admin_user';
	
	public $validate = array(
		'admin_user' => array(
			'isUnique' => array(
				'rule' => 'isUnique',
				'message' => 'The username has already been taken.',
				'last' => true,
			),
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'This field cannot be left blank.',
				'last' => true,
			)
		),
		'admin_email' => array(
        	'rule'    => array('email', true),
        	'message' => 'Please supply a valid email address.'
        ),
		'admin_pass' => array(
			'rule' => array('minLength', 6),
			'message' => 'Passwords must be at least 6 characters long.',
		),
	);
	
	public $hasMany = array(
		'AdminRole' => array(
			'className' => 'AdminRole',
			'foreignKey' => 'admin_id'
		),
		'AdminGroup' => array(
			'className' => 'AdminGroup',
			'foreignKey' => 'admin_id'
		)
	);

	public function beforeSave($options = array()) {		
		if (isset($this->data[$this->alias]['admin_pass']) && !empty($this->data[$this->alias]['admin_pass'])) {
			$this->data[$this->alias]['admin_pass'] = sha1($this->data[$this->alias]['admin_pass']);
		}
		if (isset($this->data[$this->alias]['admin_pass_temp']) && !empty($this->data[$this->alias]['admin_pass_temp'])) {
			$this->data[$this->alias]['admin_pass_temp'] = AuthComponent::password($this->data[$this->alias]['admin_pass_temp']);
		}
				
		return true;
	}
	
	function afterSave($created, $options = array()) {
		
		if (isset($this->data['AdminRole'])) {
			if (!$created) {
				$admin_roles = $this->AdminRole->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'AdminRole.admin_id' => $this->id
					),
					'fields' => array('AdminRole.id')
				));
				if ($admin_roles) {
					foreach ($admin_roles as $role) {
						$this->AdminRole->delete($role['AdminRole']['id']); 
					}
				}
			}
			if (!empty($this->data['AdminRole']['id'])) {
				foreach ($this->data['AdminRole']['id'] as $role_id) {
					$this->AdminRole->create();
					$this->AdminRole->save(array('AdminRole' => array(
						'admin_id' => $this->id,
						'role_id' => $role_id,
					)));
				}
			}
		}
		if (isset($this->data['AdminGroup'])) {
			if (!$created) {
				$admin_groups = $this->AdminGroup->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'AdminGroup.admin_id' => $this->id
					),
					'fields' => array('AdminGroup.id')
				));
				if ($admin_groups) {
					foreach ($admin_groups as $group) {
						$this->AdminGroup->delete($group['AdminGroup']['id']); 
					}
				}
			}
			if (!empty($this->data['AdminGroup']['id'])) {
				foreach ($this->data['AdminGroup']['id'] as $group_id) {
					$this->AdminGroup->create();
					$this->AdminGroup->save(array('AdminGroup' => array(
						'admin_id' => $this->id,
						'group_id' => $group_id,
					)));
				}
			}	
		}
	}

	function afterDelete() {
		$admin_roles = $this->AdminRole->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'AdminRole.admin_id' => $this->id
			),
			'fields' => array('AdminRole.id')
		));
		if ($admin_roles) {
			foreach ($admin_roles as $role) {
				$this->AdminRole->delete($role['AdminRole']['id']); 
			}
		}
		
		$admin_groups = $this->AdminGroup->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'AdminGroup.admin_id' => $this->id
			),
			'fields' => array('AdminGroup.id')
		));
		if ($admin_groups) {
			foreach ($admin_groups as $group) {
				$this->AdminGroup->delete($group['AdminGroup']['id']); 
			}
		}
		App::import('Model', 'ProjectAdmin');
		$this->ProjectAdmin = new ProjectAdmin;
		$project_admins = $this->ProjectAdmin->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'ProjectAdmin.admin_id' => $this->id
			),
			'fields' => array('ProjectAdmin.id')
		));
		if ($project_admins) {
			foreach ($project_admins as $admin) {
				$this->ProjectAdmin->delete($admin['ProjectAdmin']['id']); 
			}
		}
	}
	
	public function groups($admin_user) {
		if ($admin_user['AdminRole']['admin'] == true) {
			App::import('Model', 'Group');
			$this->Group = new Group;
			return $this->Group->find('list', array(
				'fields' => array('Group.id', 'Group.name'),
				'order' => 'Group.name ASC'
			));
		}
		$this->AdminGroup->bindModel(array('belongsTo' => array('Group')));
		$groups = $this->AdminGroup->find('all', array(
			'fields' => array('Group.id', 'Group.name'),
			'conditions' => array(
				'AdminGroup.admin_id' => $admin_user['Admin']['id']
			),
			'order' => 'Group.name ASC'
		));
		$list = array();
		if ($groups) {
			foreach ($groups as $group) {
				$list[$group['Group']['id']] = $group['Group']['name']; 
			}
		}
		return $list;
	}
	
	public function projects($id) {
		$this->bindModel(array(
			'hasMany' => array('ProjectAdmin')
		));
		$admin = $this->find('first', array(
			'contain' => array(
				'ProjectAdmin'
			),
			'conditions' => array(
				'Admin.id' => $id
			)
		));
		if (empty($admin['ProjectAdmin'])) {
			return false;
		}
		
		$projects = array();
		foreach ($admin['ProjectAdmin'] as $project_admin) {
			$projects[] = $project_admin['project_id'];
		}
		
		return $projects;
	}
	
	
	public function roles($admin) {
		$this->AdminRole->bindModel(array('belongsTo' => array('Role')));
		$roles = $this->AdminRole->find('all', array(
			'fields' => array('Role.id', 'Role.key'),
			'conditions' => array(
				'AdminRole.admin_id' => $admin['Admin']['id']
			),
			'order' => 'Role.key ASC'
		));
		$list = array();
		if ($roles) {
			foreach ($roles as $role) {
				$list[$role['Role']['id']] = $role['Role']['key']; 
			}
		}
		return $list;
	}
}