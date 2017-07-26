<?php
App::uses('AppModel', 'Model');

class CodeRedemption extends AppModel {
	public $actsAs = array('Containable');

	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id'
		),
		'Code' => array(
			'className' => 'Code',
			'foreignKey' => 'code_id'
		)
	);
}