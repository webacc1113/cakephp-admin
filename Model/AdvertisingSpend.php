<?php
App::uses('AppModel', 'Model');

class AdvertisingSpend extends AppModel {
	
	public $belongsTo = array(
		'AcquisitionPartner' => array(
			'className' => 'AcquisitionPartner',
			'foreignKey' => 'acquisition_partner_id',
			'order' => 'AcquisitionPartner.name ASC'
		)
	);
	
	public $validate = array(
		'acquisition_partner_id' => array(
		    'rule' => 'notEmpty',
			'message' => 'Please select acquisition partner.'
		),
		'date' => array(
			'date' => array(
				'rule' => 'date',
				'message' => 'Please provide a valid date.',
				'allowEmpty' => false
			),
			'checkUnique' => array(
				'rule' => 'checkUnique',
				'message' => 'Advertising spend has already set for selected acquisition partner for this date and country.'
			)
		),
		'spend' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'Please provide advertising spend amount.'
			),
			'numeric' => array(
				'rule' => 'numeric',
				'message' => 'Please provide a valid amount.',
				'allowEmpty' => false
			)
		)
    );
	
	public function checkUnique() {
		$conditions = array(
			'AdvertisingSpend.acquisition_partner_id' => $this->data['AdvertisingSpend']['acquisition_partner_id'],
			'AdvertisingSpend.date' => $this->data['AdvertisingSpend']['date'],
			'AdvertisingSpend.country' => $this->data['AdvertisingSpend']['country']
		);
		if (isset($this->data['AdvertisingSpend']['id'])) {
			$conditions['AdvertisingSpend.id <>'] = $this->data['AdvertisingSpend']['id'];
		}
		$count = $this->find('count', array(
			'conditions' => $conditions
		));
		return ($count == 0);
	}
}