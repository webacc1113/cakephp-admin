<?php
App::uses('AppModel', 'Model');

class Report extends AppModel {
	public $actsAs = array('Containable');
	
	var $belongsTo = array(
		'Admin' => array(
			'className' => 'Admin',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'admin_user', 'admin_email')
		),
		'Partner' => array(
			'className' => 'Partner',
			'foreignKey' => 'partner_id',
			'fields' => array('id', 'partner_name')
		),
		'Project' => array(
			'className' => 'Project',
			'foreignKey' => 'survey_id',
		)
	);
	
	public function beforeSave($options = array()) {		
		if (isset($this->data[$this->alias]['hashes']) && !empty($this->data[$this->alias]['hashes'])) {
			$this->data[$this->alias]['hashes'] = trim($this->data[$this->alias]['hashes']);
		}
		if (isset($this->data[$this->alias]['hashes']) && empty($this->data[$this->alias]['hashes'])) {
			$this->data[$this->alias]['hashes'] = null;
		}
		return true;
	}
}