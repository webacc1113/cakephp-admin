<?php
App::uses('AppModel', 'Model');

class VirtualMassAdd extends AppModel {
	public $useTable = false;
	
	public $validate = array(
		'identifier_type' => array(
			'required' => true,
			'rule' => 'notEmpty',
			'message' => 'Please choose an identifier type'
		),
		'amount' => array(
			'required' => true,
			'rule' => 'naturalNumber',
			'message' => 'Please input a valid amount'
		),
		'description' => array(
			'required' => true,
			'rule' => 'notEmpty',
			'message' => 'Please input a description'
		),
		'inputs' => array(
			'required' => true,
			'rule' => array('userIdentifiers'),
			'message' => 'Please make sure the user identifier type matches the values.'
		)
	); 
	
	public function userIdentifiers() {
		$inputs = explode("\n", trim($this->data[$this->alias]['inputs'])); 
		if (empty($inputs)) {
			return false;
		}
		array_walk($inputs, create_function('&$val', '$val = trim($val);')); 
		if ($this->data[$this->alias]['identifier_type'] == 'user_id') {
			foreach ($inputs as $input) {
				if (!is_numeric($input)) {
					return false;
				}
			}
		}
		if ($this->data[$this->alias]['identifier_type'] == 'partner_user_id') {
			foreach ($inputs as $input) {
				if (strpos($input, '-') === false) {
					return false;
				}
			}
		}
		if ($this->data[$this->alias]['identifier_type'] == 'hash') {
			foreach ($inputs as $input) {
				$project_id = Utils::parse_project_id_from_hash($input);
				if (!is_numeric($project_id)) {
					return false;
				}
			}
		}
		if ($this->data[$this->alias]['identifier_type'] == 'email') {
			foreach ($inputs as $input) {
				if (!Validation::email($input)) {
					return false;
				}
			}
		}
		return true;
	}
}
