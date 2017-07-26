<?php
App::uses('AppModel', 'Model');

class DailyAnalysisProperty extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$this->data[$this->alias]['name'] = trim($this->data[$this->alias]['name']); 
			if (empty($this->data[$this->alias]['name'])) {
				return false;
			}
			$count = $this->find('count', array(
				'conditions' => array(
					'DailyAnalysisProperty.name' => $this->data[$this->alias]['name']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
