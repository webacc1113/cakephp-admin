<?php
App::uses('AppModel', 'Model');

class SurveyReport extends AppModel {
	public $actsAs = array('Containable');
	
	public $belongsTo = array(
		'Partner' => array(
			'className' => 'Partner',
			'foreignKey' => 'partner_id',
			'fields' => array('partner_name')
		),
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'username', 'email')
		),
    );
}
