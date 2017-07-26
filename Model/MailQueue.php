<?php
App::uses('AppModel', 'Model');

class MailQueue extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id']) && (!isset($this->data[$this->alias]['shard']) || empty($this->data[$this->alias]['shard']))) {
			$this->data[$this->alias]['shard'] = rand(1, 4);
		}
		return true;
	}
}
