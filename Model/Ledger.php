<?php
App::uses('AppModel', 'Model');

class Ledger extends AppModel {
	public $useTable = 'groupon_ledger';
	public $actsAs = array('Containable');
	
	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id'
		),
	);
}