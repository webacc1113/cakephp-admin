<?php
App::uses('AppModel', 'Model');

class Points2shopQueue extends AppModel {
	public function beforeSave($options = array()) {
		// creating a new task
		if (!isset($this->data['Points2shopQueue']['id']) && (!isset($this->data['Points2shopQueue']['executed']) || empty($this->data['Points2shopQueue']['executed']))) {
			$existing_queue = $this->find('first', array(
				'conditions' => array(
					'Points2shopQueue.points2shop_survey_id' => $this->data['Points2shopQueue']['points2shop_survey_id'],
					'Points2shopQueue.command' => $this->data['Points2shopQueue']['command']
				),
				'order' => 'Points2shopQueue.id DESC'
			));
			// if not executed, definitely do not add
			if ($existing_queue && is_null($existing_queue['Points2shopQueue']['executed'])) {
				return false;
			}
			// if the project has been updated within the past 15 minutes, skip it
			if ($existing_queue && strtotime('-15 minutes ago') <= strtotime($existing_queue['Points2shopQueue']['created'])) {
				return false;
			}
		}
		return true;
	}
}
