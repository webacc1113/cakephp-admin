<?php
App::uses('AppModel', 'Model');
App::uses('AuthComponent', 'Controller/Component');

class Role extends AppModel {
	
	public $actsAs = array('Containable');
	
	public $hasMany = array(
		'AdminRole' => array(
			'className' => 'AdminRole',
			'foreignKey' => 'role_id'
		),
	);
	
	public function beforeSave($options = array()) {
		
		// save they key on add
		if (!isset($this->data[$this->alias]['id'])) {
			$this->data[$this->alias]['key'] = Inflector::slug(strtolower($this->data[$this->alias]['name']), '_');
		}
		
		return true;
	}
	
	public function get_administrators($role_keys = null) {
		$this->AdminRole->bindModel(array(
			'belongsTo' => array('Admin')
		));
		$roles = $this->find('all', array(
			'contain' => array(
				'AdminRole' => array(
					'Admin'
				)
			),
			'conditions' => array(
				'Role.key' => $role_keys,
			)
		));
		
		if (!$roles) {
			return false;
		}
		
		$admins = array();
		foreach ($roles as $role) {
			if (!empty($role['AdminRole'])) {
				foreach ($role['AdminRole'] as $admin_role) {
					if ($admin_role['Admin']['active']) {
						$admins[$admin_role['Admin']['id']] = $admin_role['Admin']['admin_user'];
					}
				}
			}
		}
		natcasesort($admins);
		return $admins;
	}
	
}