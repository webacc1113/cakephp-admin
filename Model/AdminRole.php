<?php
App::uses('AppModel', 'Model');

class AdminRole extends AppModel {
	
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {		
		$count = $this->find('count', array(
			'conditions' => array(
				'AdminRole.admin_id' => $this->data[$this->alias]['admin_id'],
				'AdminRole.role_id' => $this->data[$this->alias]['role_id'],
			)
		));
		if ($count) {
			return false;
		}
		
		return true;
	}

}