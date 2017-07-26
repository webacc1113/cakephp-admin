<?php
App::uses('AppModel', 'Model');

class CintRegion extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'CintRegion.cint_id' => $this->data[$this->alias]['cint_id']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
