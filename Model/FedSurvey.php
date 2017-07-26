<?php
App::uses('AppModel', 'Model');

class FedSurvey extends AppModel {
	public $actsAs = array('Containable');
	
	
	public function beforeSave($options = array()) {		
		if (!isset($this->data[$this->alias]['id'])) {
			if (empty($this->data[$this->alias]['survey_id'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'FedSurvey.survey_id' => '0',
						'FedSurvey.fed_survey_id' => $this->data[$this->alias]['fed_survey_id']
					)
				));
				if ($count > 0) {
					return false;
				}
			}
		}
		return true;
	}
}
