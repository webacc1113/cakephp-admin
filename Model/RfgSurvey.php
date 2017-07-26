<?php
App::uses('AppModel', 'Model');

class RfgSurvey extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			if (empty($this->data[$this->alias]['survey_id'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'RfgSurvey.survey_id' => '0',
						'RfgSurvey.rfg_survey_id' => $this->data[$this->alias]['rfg_survey_id']
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
