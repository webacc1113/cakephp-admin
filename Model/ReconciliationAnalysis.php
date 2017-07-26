<?php
App::uses('AppModel', 'Model');

class ReconciliationAnalysis extends AppModel {
	
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		
		// Data in this table should not duplicate - possible occurrence can be, when the same report is reconciled twice.
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'hash' => $this->data[$this->alias]['hash'],
					'user_id' => $this->data[$this->alias]['user_id'],
					'survey_id' => $this->data[$this->alias]['survey_id'],
				)
			));
			if ($count > 0) {
				return false;
			}
			
		}
		
		return true;
	}
}
