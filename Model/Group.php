<?php
App::uses('AppModel', 'Model');

class Group extends AppModel {

	public $displayField = 'name';
	
	public $validate = array(
		'client_name' => array(
		    'rule' => 'notEmpty'
		),
		'key' => array(
			'isUnique' => array(
				'rule' => 'isUnique',			
				'message' => 'Provide a unique group key'
			)
		),
		'prefix' => array(
			'isUnique' => array(
				'allowEmpty' => true,
				'rule' => 'isUnique',			
				'message' => 'Provide a unique group prefix'
			)
		),
		'router_priority' => array(
			'rule' => array('validateRouterPriority'),
			'message' => 'Router Priority must be greater then 0.',
		),
	);
	
	function afterDelete() {
		App::import('Model', 'AdminGroup');
		$this->AdminGroup = new AdminGroup;
		$admin_groups = $this->AdminGroup->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'AdminGroup.group_id' => $this->id
			),
			'fields' => array('AdminGroup.id')
		));
		if ($admin_groups) {
			foreach ($admin_groups as $group) {
				$this->AdminGroup->delete($group['AdminGroup']['id']); 
			}
		}
	}
	
	public function validateRouterPriority() {
		if (isset($this->data[$this->alias]['router_priority']) && $this->data[$this->alias]['router_priority'] <= 0) {
			return false;
		}
		
		return true;
	}
}