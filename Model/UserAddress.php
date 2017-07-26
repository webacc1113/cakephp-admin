<?php
App::uses('AppModel', 'Model');

class UserAddress extends AppModel {

	public $actsAs = array('Containable');
	
	public $validate = array(
		'first_name' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'last_name' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'country' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'postal_code' => array(
			'rule' => array('validateZip'),
			'allowEmpty' => false,
			'message' => 'invalid ZIP/postal code.',
		),
		'city' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'address_line1' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'county' => array(
			'rule' => 'notEmpty',
			'required' => false
		),
	);
	
	public function validateZip() {
		if (isset($this->data[$this->alias]['country'])) {
			$this->data[$this->alias]['postal_code'] = trim($this->data[$this->alias]['postal_code']);
			if ($this->data[$this->alias]['country'] == 'US') {
				App::import('Model', 'GeoZip');
				$this->GeoZip = new GeoZip;
				$count = $this->GeoZip->find('count', array(
					'conditions' => array(
						'GeoZip.zipcode' => $this->data[$this->alias]['postal_code'],
						'GeoZip.country_code' => 'US'
					)
				));
				return $count > 0;
			}
			elseif ($this->data[$this->alias]['country'] == 'CA') { //canada
				if (!strpos($this->data[$this->alias]['postal_code'], ' ')) { // Add space after 3rd charactor if not already
					$this->data[$this->alias]['postal_code'] = substr($this->data[$this->alias]['postal_code'], 0, 3) . " " . substr($this->data[$this->alias]['postal_code'], 3);
				}
				$this->data[$this->alias]['postal_code'] = strtoupper($this->data[$this->alias]['postal_code']);
				return preg_match("/^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ] ?\d[ABCEGHJKLMNPRSTVWXYZ]\d$/", $this->data[$this->alias]['postal_code']);
			}
			elseif ($this->data[$this->alias]['country'] == 'GB') { //uk
				return Utils::checkUkPostcode($this->data[$this->alias]['postal_code']);
			}
		}
		return true;
	}
}
