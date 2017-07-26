<?php
App::uses('AppModel', 'Model');

class SurveyFlag extends AppModel {
	public $actsAs = array('Containable');	
	var $belongsTo = array(
		'SurveyUserVisit' => array(
			'className' => 'SurveyUserVisit',
			'foreignKey' => 'survey_user_visit_id',
		)
	);
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['flag']) && isset($this->data[$this->alias]['survey_user_visit_id'])) {
			$count = $this->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'SurveyFlag.flag' => $this->data[$this->alias]['flag'],
					'SurveyFlag.survey_user_visit_id' => $this->data[$this->alias]['survey_user_visit_id'],
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
