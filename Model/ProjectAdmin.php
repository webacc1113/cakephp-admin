<?php
App::uses('AppModel', 'Model');

class ProjectAdmin extends AppModel {
	
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		
		// Check only on edit
		if (!isset($this->data[$this->alias]['id'])) {
				$count = $this->find('count', array(
				'conditions' => array(
					'ProjectAdmin.admin_id' => $this->data[$this->alias]['admin_id'],
					'ProjectAdmin.project_id' => $this->data[$this->alias]['project_id'],
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		
		return true;
	}

}