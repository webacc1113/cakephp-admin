<?php

App::uses('AppModel', 'Model');

class PaymentMethod extends AppModel {

	public $displayField = 'payment_method';
	public $actsAs = array('Containable');
	
	public $validate = array(
		'value' => array(
			'notBlank' => array(
				'rule' => 'notEmpty',
				'message' => 'can not be left blank.',
			),
			'custom' => array(
				'rule' => array('validateValue'),
				'message' => 'This email has already been linked to another MintVine account and thus cannot be used for this account.'
			)
		)
	);
	
	public function validateValue() {
		// todo: this check should be skipped for tango and gifts
		
		// validate that this value hasn't been used before
		$count = $this->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'PaymentMethod.value' => $this->data[$this->alias]['value'],
				'PaymentMethod.payment_method' => $this->data[$this->alias]['payment_method'],
				'PaymentMethod.user_id <>' => $this->data[$this->alias]['user_id']
			)
		));
		
		if ($count > 0) {
			return false;
		}
		else {
			return true;
		}
	}

	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			// if we're creating a new payment method, deactivate all other payment methods for this user
			$this->updateAll(
				array('PaymentMethod.status' => '"' . DB_DEACTIVE . '"'), 
					array(
						'PaymentMethod.status' => DB_ACTIVE,
						'PaymentMethod.user_id' => $this->data[$this->alias]['user_id']
					)
				);
		}
		return true;
	}

}
