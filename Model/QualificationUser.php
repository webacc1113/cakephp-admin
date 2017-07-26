<?php
App::uses('AppModel', 'Model');

class QualificationUser extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeDelete($cascade = true) {
		$qualification_user_id = $this->id; 
		$this->create();
		$this->save(array('QualificationUser' => array(
			'id' => $qualification_user_id,
			'deleted' => true
		)), array(
			'callbacks' => false,
			'validate' => false,
			'fieldList' => array('deleted')
		));
		return false;
	}
}