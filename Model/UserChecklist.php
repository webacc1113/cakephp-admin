<?php
App::uses('AppModel', 'Model');

class UserChecklist extends AppModel {
	public $actsAs = array('Containable');	
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'UserChecklist.user_id' => $this->data[$this->alias]['user_id'],
					'UserChecklist.name' => $this->data[$this->alias]['name'],
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
