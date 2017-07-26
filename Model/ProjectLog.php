<?php
App::uses('AppModel', 'Model');
class ProjectLog extends AppModel {
	var $belongsTo = array(
		'Admin' => array(
			'className' => 'Admin',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'admin_user', 'admin_email')
		),
		'Project' => array(
			'className' => 'Project',
			'foreignKey' => 'project_id',
			'fields' => array('id', 'mask', 'group_id')
		),
	);
}
