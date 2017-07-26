<?php
App::uses('AppModel', 'Model');

class Code extends AppModel {
	public $actsAs = array('Containable');

	public $validate = array(
		'code' => array(
			'alphaNumeric' => array(
				'rule' => 'alphaNumeric',
				'message' => 'Must be a alphanumeric value'
			),
			'checkUnique' => array(
				'rule' => 'checkUnique',
				'message' => 'This code already exists'
			)
		),
		'amount' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty'
			),
			'numeric' => array(
				'rule' => 'numeric',
				'message' => 'Must be a numeric value'
			)
		),
		'quota' => array(
			'rule' => 'numeric',
			'message' => 'Must be a numeric value',
			'allowEmpty' => true
		)
	);

	public function checkUnique() {
		$conditions = array(
			'Code.code' => $this->data['Code']['code']
		);
		if (isset($this->data['Code']['id'])) {
			$conditions['Code.id <>'] = $this->data['Code']['id'];
		}
		$count = $this->find('count', array(
			'conditions' => $conditions
		));
		return ($count == 0);
	}
}
