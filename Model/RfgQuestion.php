<?php
App::uses('AppModel', 'Model');

class RfgQuestion extends AppModel {
	public $actsAs = array('Containable');
	
	public $hasMany = array('RfgAnswer' => array(
		'class' => 'RfgAnswer'
	));
}
