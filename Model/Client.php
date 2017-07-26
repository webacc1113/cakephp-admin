<?php
App::uses('AppModel', 'Model');

class Client extends AppModel {
	public $actsAs = array('Containable');		
	
	public $displayField = 'client_name';
	
	public $hasOne = array(
		'Contact' => array(
			'className' => 'Contact',
			'foreignKey' => 'linked_to_id',
			'conditions' => array('Contact.contact_type' => 'Client')
		),
	);
	
	public $belongsTo = array(
		'Group' => array(
			'className' => 'Group',
			'foreignKey' => 'group_id'
		),
		'GeoState' => array(
			'className' => 'GeoState',
			'foreignKey' => 'geo_state_id'
		),
		'GeoCountry' => array(
			'className' => 'GeoCountry',
			'foreignKey' => 'geo_country_id'
		),
	);
	
	public $validate = array(
		'client_name' => array(
		    'rule' => 'notEmpty'
		),
		'group_id' => array(
		    'rule' => 'notEmpty'
		),
		'project_email' => array(
		    'rule' => 'email',
			'allowEmpty' => true,
			'message' => 'You did not input a valid email address.'
		),
		'billing_email' => array(
		    'rule' => 'email',
			'allowEmpty' => true,
			'message' => 'You did not input a valid email address.'
		),
    );
	
	function beforeSave($options = array()) {
		if (!empty($this->data['Client']['address_line1'])) {
			App::import('Model', 'GeoState');
			$this->GeoState = new GeoState();
			$state = $this->GeoState->find('first', array(
				'conditions' => array(
					'GeoState.id' => $this->data['Client']['geo_state_id']
				)
			));
			if ($state && $state['GeoState']['state_abbr'] != 'N/A') {
				$state = $state['GeoState']['state_abbr'] . ' ';
			}
			else {
				App::import('Model', 'GeoCountry');
				$this->GeoCountry = new GeoCountry();
				$country = $this->GeoCountry->find('first', array(
					'conditions' => array(
						'GeoCountry.id' => $this->data['Client']['geo_country_id']
					)
				));
				if ($country) {
					$state = $country['GeoCountry']['country'] . ' ';
				}
				else {
					$state = null;
				}
			}
			$address = $this->data['Client']['address_line1'] . "\n" . $this->data['Client']['address_line2'] . "\n" . $this->data['Client']['city'] . ', ' . $state . !empty($this->data['Client']['postal_code']) ? $this->data['Client']['postal_code'] : null;
			$this->data['Client']['notes'] = $address;
		}
		
		return true;
	}
}
