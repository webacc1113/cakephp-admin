<?php
App::uses('AppModel', 'Model');

class AcquisitionPartner extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['source'])) {
			$this->data[$this->alias]['source'] = trim($this->data[$this->alias]['source']);
		}
		return true;
	}
}
