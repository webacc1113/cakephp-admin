<?php
App::uses('AppModel', 'Model');

class QueryProfile extends AppModel {
	public $actsAs = array('Containable');
	
	public $validate = array(
		'birthdate' => array(
			'allowEmpty' => true,
			'rule' => array('validateBirthdate'),
			'message' => 'You must be at least 14 to participate in MintVine.',
		),
		'gender' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'Please select your gender.'
			)
		),
		'postal_code' => array(
			'rule' => array('validateZip'),
			'allowEmpty' => false,
			'message' => 'You input an invalid ZIP/postal code.',
		)	
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
					),
					'recursive' => -1
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
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['postal_code']) && isset($this->data[$this->alias]['country'])) {
			if ($this->data[$this->alias]['country'] == 'GB') {
				$this->data[$this->alias]['postal_code'] = Utils::format_uk_postcode($this->data[$this->alias]['postal_code']);
			}
			elseif ($this->data[$this->alias]['country'] == 'CA') {
				$this->data[$this->alias]['postal_code'] = Utils::format_ca_postcode($this->data[$this->alias]['postal_code']);
			}
		}
	}
	
	public function validateBirthdate() {
		$dob =  isset($this->data[$this->alias]['birthdate']) ? $this->data[$this->alias]['birthdate']: null;
		if (empty($dob)) {
			return false;
		}
		$cutoff = strtotime('-13 years');
		if (date('Y-m-d', $cutoff) < date('Y-m-d', strtotime($dob))) {
			return false;
		}
		return true;
	}
}
