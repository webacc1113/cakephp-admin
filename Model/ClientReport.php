<?php
App::uses('AppModel', 'Model');

class ClientReport extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');
	
	public $belongsTo = array(
		'Partner' => array(
			'className' => 'Partner',
			'foreignKey' => 'partner_id'
		),
		'Project' => array(
			'className' => 'Project',
			'foreignKey' => 'survey_id'
		)
	);
}
