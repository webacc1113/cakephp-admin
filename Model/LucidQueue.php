<?php
App::uses('AppModel', 'Model');

class LucidQueue extends AppModel {
	
	public function beforeSave($options = array()) {
		// creating a new task
		if (!isset($this->data['LucidQueue']['id']) && (!isset($this->data['LucidQueue']['executed']) || empty($this->data['LucidQueue']['executed']))) {
			$existing_queue = $this->find('first', array(
				'conditions' => array(
					'LucidQueue.fed_survey_id' => $this->data['LucidQueue']['fed_survey_id'],
					'LucidQueue.command' => $this->data['LucidQueue']['command']
				),
				'order' => 'LucidQueue.id DESC'
			));
			// if not executed, definitely do not add
			if ($existing_queue && is_null($existing_queue['LucidQueue']['executed'])) {
				return false;
			}
			// if the project has been updated within the past 15 minutes, skip it
			if ($existing_queue && strtotime('-15 minutes ago') <= strtotime($existing_queue['LucidQueue']['created'])) {
				return false;
			}
		}
		return true;
	}
}
