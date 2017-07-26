<?php
App::uses('AppModel', 'Model');

class SsiUser extends AppModel {
	public $actsAs = array('Containable');	
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'SsiUser.user_id' => $this->data[$this->alias]['user_id']
				),
				'recursive' => -1
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
