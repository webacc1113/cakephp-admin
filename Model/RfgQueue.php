<?php
App::uses('AppModel', 'Model');

class RfgQueue extends AppModel {
	
	public function beforeSave($options = array()) {
		// creating a new task
		if (!isset($this->data['RfgQueue']['id']) && (!isset($this->data['RfgQueue']['executed']) || empty($this->data['RfgQueue']['executed']))) {
			$existing_queue = $this->find('first', array(
				'conditions' => array(
					'RfgQueue.rfg_survey_id' => $this->data['RfgQueue']['rfg_survey_id'],
					'RfgQueue.command' => $this->data['RfgQueue']['command']
				),
				'order' => 'RfgQueue.id DESC'
			));
			// if not executed, definitely do not add
			if ($existing_queue && is_null($existing_queue['RfgQueue']['executed'])) {
				return false;
			}
			// if the project has been updated within the past 15 minutes, skip it
			if ($existing_queue && strtotime('-15 minutes ago') <= strtotime($existing_queue['RfgQueue']['created'])) {
				return false;
			}
		}
		return true;
	}
}
