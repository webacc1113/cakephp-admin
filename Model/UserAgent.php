<?php

class UserAgent extends AppModel {
	public $actsAs = array('Containable');
 
	public $hasMany = array(
		'UserAgentValue' => array(
			'className' => 'UserAgentValue',
			'foreignKey' => 'user_agent_id',
			'fields' => array('name', 'value')
		)
	);
}