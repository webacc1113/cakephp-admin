<?php
App::uses('AppModel', 'Model');

class PooledPoint extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'PooledPoint.survey_id' => $this->data[$this->alias]['survey_id'],
					'PooledPoint.user_id' => $this->data[$this->alias]['user_id']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}