<?php
App::uses('AppModel', 'Model');

class Tangocard extends AppModel {
	
	public $actsAs = array('Containable');
    
	public $belongsTo = array(
		'Parent' => array(
			'className' => 'Tangocard',
			'foreignKey' => 'parent_id'
		)
	);
	
	public $hasMany = array(
		'Children' => array(
			'className' => 'Tangocard',
			'foreignKey' => 'parent_id',
			'conditions' => array(
				'deleted' => false
			)
		)
	);
	
	public $validate = array(
        'name' => array(
            'rule' => 'notEmpty'
        ),
        'sku' => array(
            'rule' => 'validateSku',
			'message' => 'SKU can not be left empty for actual(non parent) card.'
		),
        'value' => array(
			'rule1' => array(
				'rule' => 'numeric',
				'required' => false,
				'allowEmpty' => true,
				'message' => 'Value is allowed in cents only.'
			),
			'rule2' => array(
				'rule' => 'validateValue',
				'message' => 'Can be set for fixed price cards only.'
			)
		),
        'min_value' => array(
			'rule1' => array(
				'rule' => 'numeric',
				'required' => false,
				'allowEmpty' => true,
				'message' => 'Value is allowed in cents only.'
			),
			'rule2' => array(
				'rule' => 'validateValue',
				'message' => 'can be set for variable price cards only.'
			)
		),
        'max_value' => array(
            'rule1' => array(
				'rule' => 'numeric',
				'required' => false,
				'allowEmpty' => true,
				'message' => 'Value is allowed in cents only.'
			),
			'rule2' => array(
				'rule' => 'validateValue',
				'message' => 'can be set for variable price cards only.'
			)
		),
		'currency' => array(
			'rule' => 'validateCurrency',
			'required' => false,
			'allowEmpty' => true,
			'message' => 'Currency must be set for actual (non parent) card.'
		),
		'conversion' => array(
			'rule' => array('decimal'),
			'required' => false,
			'allowEmpty' => true,
			'message' => 'Conversion is allowed in decimal only.'
		)
    );
	
	public function validateSku() {
		if ((!empty($this->data[$this->alias]['value']) || !empty($this->data[$this->alias]['min_value'])) && empty($this->data[$this->alias]['sku'])) {
			return false;
		}
		
		return true;
	}
	
	public function validateValue() {
		if ((!empty($this->data[$this->alias]['max_value']) || !empty($this->data[$this->alias]['min_value'])) && !empty($this->data[$this->alias]['value'])) {
			return false;
		}
		
		return true;
	}

	public function validateCurrency() {
		if (!empty($this->data[$this->alias]['sku']) && empty($this->data[$this->alias]['currency'])) {
			return false;
		}
		
		return true;
	}
}