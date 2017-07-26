<?php
App::uses('AppModel', 'Model');

class SurveyUserVisit extends AppModel {
	public $actsAs = array('Containable');
	
	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'username', 'email', 'hellbanned', 'balance', 'pending')
		),
		'Project' => array(
			'className' => 'Project',
			'foreignKey' => 'survey_id',
			'fields' => array('id', 'prj_name', 'survey_name')
		),
	);
	
	public $hasMany = array(
		'SurveyFlag' => array(
			'className' => 'SurveyFlag',
			'foreignKey' => 'survey_user_visit_id',
			'fields' => array('flag', 'passed', 'description')
		)
	);	
	
	function findAllCompleteByUser($user_id) {
		return $this->find('all', array(
			'conditions' => array(
				'SurveyUserVisit.user_id' => $user_id,
				'SurveyUserVisit.status' => SURVEY_COMPLETED
			)
		));
	}
	
	
}
