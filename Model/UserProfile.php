<?php
App::uses('AppModel', 'Model');

class UserProfile extends AppModel {
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'UserProfile.user_id' => $this->data[$this->alias]['user_id'],
					'UserProfile.profile_id' => $this->data[$this->alias]['profile_id']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
