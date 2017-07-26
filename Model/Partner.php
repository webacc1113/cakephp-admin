<?php
App::uses('AppModel', 'Model');

class Partner extends AppModel {
	public $actsAs = array('Containable');
	
	public $displayField = 'partner_name';
	
	public $validate = array(
        'partner_name' => array(
            'rule' => 'notEmpty'
        ),
    );
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$this->data[$this->alias]['code'] = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
		}
	}
}
