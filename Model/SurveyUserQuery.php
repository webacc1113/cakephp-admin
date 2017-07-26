<?php
App::uses('AppModel', 'Model');

class SurveyUserQuery extends AppModel {
	public $actsAs = array('Containable');
	public $validate = array(
		'survey_user_id' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'query_history_id' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
	);
	var $belongsTo = array(
		'QueryHistory'
	);
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'SurveyUserQuery.survey_user_id' => $this->data[$this->alias]['survey_user_id'],
					'SurveyUserQuery.query_history_id' => $this->data[$this->alias]['query_history_id'],
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
