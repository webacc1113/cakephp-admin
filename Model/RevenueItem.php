<?php
App::uses('AppModel', 'Model');

class RevenueItem extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id']) && isset($this->data[$this->alias]['transaction_id'])) {
			$count = $this->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'RevenueItem.transaction_id' => $this->data[$this->alias]['transaction_id']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
