<?php 
class HistoryRequest extends AppModel {
	public $actsAs = array('Containable');
	
	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'username', 'email', 'hellbanned', 'balance', 'pending', 'timezone')
		),
		'Project' => array(
			'className' => 'Project',
			'foreignKey' => 'project_id',
			'fields' => array('id', 'prj_name', 'survey_name', 'award', 'router')
		)
	);
}