<?php
App::uses('AppModel', 'Model');
App::uses('AuthComponent', 'Controller/Component');

class ApiUser extends AppModel {
	public $actsAs = array('Containable');
	public $displayField = 'username';

	public $validate = array(
		'admin_user' => array(
			'isUnique' => array(
				'rule' => 'isUnique',
				'message' => 'The username has already been taken.',
				'last' => true,
			),
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'This field cannot be left blank.',
				'last' => true,
			)
		),
		'password' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'This field cannot be left blank.',
				'last' => true,
			)
		),
	);

	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id']) && !isset($this->data[$this->alias]['code'])) {
			$this->data[$this->alias]['code'] = substr(md5(uniqid(rand(), true)), 0, 8);
		}
		if (isset($this->data[$this->alias]['password']) && !empty($this->data[$this->alias]['password'])) {
			$this->data[$this->alias]['password'] = Security::hash($this->data[$this->alias]['password'], 'sha1', true);
		}
		return true;
	}

	function afterSave($created, $options = array()) {

	}

	function afterDelete() {

	}
}