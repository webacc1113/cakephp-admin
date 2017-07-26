<?php
App::uses('AppModel', 'Model');

class ProjectOption extends AppModel {
	public $actsAs = array('Containable');	
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'ProjectOption.project_id' => $this->data[$this->alias]['project_id'],
					'ProjectOption.name' => $this->data[$this->alias]['name']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
