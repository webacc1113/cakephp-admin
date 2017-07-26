<?php
App::uses('AppModel', 'Model');

class Setting extends AppModel {
	public $actsAs = array('Containable');
	
	public $validate = array(
		'name' => array(
			'checkUnique' => array(
				'allowEmpty' => false,
				'required' => true,
				'rule' => 'checkUnique',
				'message' => 'The key is already in use - please edit it.'
			)
		),
		'description' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'The description is required.',
			)
		)
	);
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['value'])) {
			$this->data[$this->alias]['value'] = trim($this->data[$this->alias]['value']);
		}
		if (isset($this->data[$this->alias]['name'])) {
			$this->data[$this->alias]['name'] = trim($this->data[$this->alias]['name']);
		}
		return true;
	}
	
	public function checkUnique() {
		if (isset($this->data[$this->alias]['name'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'Setting.name' => $this->data[$this->alias]['name'],
					'Setting.deleted' => false,
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}