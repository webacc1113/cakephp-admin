<?php
App::uses('AppModel', 'Model');

class Invoice extends AppModel {
	
	public $actsAs = array('Containable');
	
	public $hasMany = array(
		'InvoiceRow' => array(
			'className' => 'InvoiceRow',
			'foreignKey' => 'invoice_id',
		),
    );
    
    public $validate = array(
        'number' => array(
            'rule' => array('validateNumber'),
			'allowEmpty' => false,
			'message' => 'An invoice with this number already exist.',
		),
        'name' => array(
            'rule' => 'notEmpty'
        ),
        'address_line1' => array(
            'rule' => 'notEmpty'
        ),
        'geo_country_id' => array(
            'rule' => 'notEmpty'
        ),
        'geo_state_id' => array(
            'rule' => 'notEmpty'
        ),
        'city' => array(
            'rule' => 'notEmpty'
        ),
        'postal_code' => array(
            'rule' => 'notEmpty'
        ),
		'email' => array(
			'rule' => 'email',
			'allowEmpty' => false,
			'message' => 'You did not input a valid email address.',
		),
		'cc' => array(
			'allowEmpty' => true,
			'rule' => array('validateEmails'),
			'message' => 'One or more email addresses are invalid.'
		),
        'client_project_reference' => array(
            'rule' => 'notEmpty'
        ),
        'subtotal' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty'			
			),
			'positiveValue' => array(
				'rule' => array('positiveValue', true, 'subtotal'),			
				'message' => 'Total must be 0 or greater'
			)
        ),
    );
	
	function positiveValue($field, $allowZero = false, $key) {
		if (isset($field[$key])) {
			if ($allowZero && $field[$key] < 0) {
				return 'Total must be 0 or greater';
			}
			else if (!$allowZero && $field[$key] <= 0)  {
				return 'Total must be greater than 0';
			}
		}
		return true;
	}
	
	public function validateEmails() {
		$email_cc = array_map('trim', explode(',', $this->data[$this->alias]['cc']));
		if (!empty($email_cc)) {
			App::uses('Validation', 'Utility');
			foreach ($email_cc as $email) {
				if (empty($email)) {
					continue;
				}
				
				if (!Validation::email($email)) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	public function validateNumber() {
		if (isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'Invoice.id <>' => $this->data[$this->alias]['id'],
					'Invoice.number' => $this->data[$this->alias]['number']
				),
				'recursive' => -1
			));
		}
		else {
			$count = $this->find('count', array(
				'conditions' => array(
					'Invoice.number' => $this->data[$this->alias]['number']
				),
				'recursive' => -1
			));
		}
		
		if ($count > 0) {
			return false;
		}
		
		return true;
	}

	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			App::uses('String', 'Utility');
			$this->data[$this->alias]['uuid'] = String::uuid();
		}
		
		if (isset($this->data[$this->alias]['date'])) {
			$this->data[$this->alias]['date'] = Utils::change_tz_to_utc($this->data[$this->alias]['date'], DB_DATETIME);
			$this->data['Invoice']['due_date'] = date('Y-m-d H:i:s', strtotime($this->data[$this->alias]['date']. '+' . $this->data[$this->alias]['terms'] . ' days'));
		}
		
		
		if (isset($this->data[$this->alias]['cc'])) {
			$email_cc = array_map('trim', explode(',', $this->data[$this->alias]['cc']));
			$emails = array();
			if ($email_cc) {
				foreach ($email_cc as $email) {
					if (empty($email)) {
						continue;
					}

					$emails[] = $email;
				}
			}

			if (!empty($emails)) {
				$this->data[$this->alias]['cc'] = implode(', ', array_unique($emails));
			}
		}
		if (!empty($this->data['Invoice']['address_line1'])) {
			App::import('Model', 'GeoState');
			$this->GeoState = new GeoState();
			$state = $this->GeoState->find('first', array(
				'conditions' => array(
					'GeoState.id' => $this->data['Invoice']['geo_state_id']
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
						'GeoCountry.id' => $this->data['Invoice']['geo_country_id']
					)
				));
				if ($country) {
					$state = $country['GeoCountry']['country'] . ' ';
				}
				else {
					$state = null;
				}
			}
			$address = array();
			if (!empty($this->data['Invoice']['address_line1'])) {
				$address[] = $this->data['Invoice']['address_line1']; 
			}	
			if (!empty($this->data['Invoice']['address_line2'])) {
				$address[] = $this->data['Invoice']['address_line2']; 
			}	
			$line3 = '';
			if (!empty($this->data['Invoice']['city'])) {
				$line3.= $this->data['Invoice']['city'].', '; 
			}	
			$line3.= $state.$this->data['Invoice']['postal_code'];
			if (!empty($line3)) {
				$address[] = $line3;
			}
			$this->data['Invoice']['address'] = implode("\n", $address);
		}

		return true;
	}
	
	function afterDelete() {
		App::import('Model', 'InvoiceRow');
		$this->InvoiceRow = new InvoiceRow;
		$invoice_rows = $this->InvoiceRow->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'InvoiceRow.invoice_id' => $this->id
			),
			'fields' => array('InvoiceRow.id')
		));
		if ($invoice_rows) {
			foreach ($invoice_rows as $invoice_row) {
				$this->InvoiceRow->delete($invoice_row['InvoiceRow']['id']); 
			}
		}
	}

}
