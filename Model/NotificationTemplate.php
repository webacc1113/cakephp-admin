<?php
App::uses('AppModel', 'Model');

class NotificationTemplate extends AppModel {
	public $actsAs = array('Containable');

	
	public $validate = array(
		'name' => array(
		    'rule' => 'notEmpty',
			'required' => true
		),
		'description' => array(
		    'rule' => 'notEmpty',
			'required' => true
		),
    );
	
	public function beforeSave($options = array()) {
		$total = 0; 
		for ($i = 0; $i < 24; $i++) {
			$key = str_pad($i, 2, '0', STR_PAD_LEFT); 
			if (!isset($key) || empty($key)) {
				$this->data['NotificationTemplate'][$key] = 0; 
			}
			$total = $total + $this->data['NotificationTemplate'][$key];
		}
		
		$this->data['NotificationTemplate']['total_emails'] = $total; 
		return true;
	}
	
}