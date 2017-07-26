<?php
App::uses('AppModel', 'Model');

class AdminGroup extends AppModel {
	
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {		
		$count = $this->find('count', array(
			'conditions' => array(
				'AdminGroup.admin_id' => $this->data[$this->alias]['admin_id'],
				'AdminGroup.group_id' => $this->data[$this->alias]['group_id'],
			)
		));
		if ($count) {
			return false;
		}
		
		return true;
	}

}