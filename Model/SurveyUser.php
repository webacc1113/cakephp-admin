<?php
App::uses('AppModel', 'Model');

class SurveyUser extends AppModel {
	
	public $actsAs = array('Containable');
	
	var $belongsTo = array(
		'Project' => array(
			'className' => 'Project',
			'foreignKey' => 'survey_id',
		),
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
		)
	);

	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$survey_user = $this->find('first', array(
				'fields' => array('SurveyUser.id'),
				'conditions' => array(
					'survey_id' => $this->data[$this->alias]['survey_id'],
					'user_id' => $this->data[$this->alias]['user_id'],
				),
				'recursive' => -1,
			));					
			if ($survey_user) {
				return false;
			}
			$this->data[$this->alias]['created'] = date(DB_DATETIME, time());
		}
		return true;
	}
}
