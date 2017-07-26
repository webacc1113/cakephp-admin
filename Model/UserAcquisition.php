<?php
App::uses('AppModel', 'Model');

class UserAcquisition extends AppModel {
	public $actsAs = array('Containable');	
	
	public function afterFind($results, $primary = false) {
		if ($primary === false) {
			if (isset($results['params'])) {
				$results['params'] = unserialize($results['params']);
			}
			if ($belongsToQuestion) {
				return array('0' => array('UserAcquisition' => $results)); 
			}
		}
		else {
			foreach ($results as $key => $val) {
				if (isset($val[$this->alias]['params'])) {
					$results[$key][$this->alias]['params'] = unserialize($val[$this->alias]['params']);
				}
			}
		}
		return $results;
	}
}
