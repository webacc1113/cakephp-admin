<?php
App::uses('AppModel', 'Model');

class SpectrumQueue extends AppModel {
	
	public function beforeSave($options = array()) {
		// creating a new task
		if (!isset($this->data['SpectrumQueue']['id']) && (!isset($this->data['SpectrumQueue']['executed']) || empty($this->data['SpectrumQueue']['executed']))) {
			$existing_queue = $this->find('first', array(
				'conditions' => array(
					'SpectrumQueue.spectrum_survey_id' => $this->data['SpectrumQueue']['spectrum_survey_id'],
					'SpectrumQueue.command' => $this->data['SpectrumQueue']['command']
				),
				'order' => 'SpectrumQueue.id DESC'
			));
			// if not executed, definitely do not add
			if ($existing_queue && is_null($existing_queue['SpectrumQueue']['executed'])) {
				return false;
			}
			// if the project has been updated within the past 15 minutes, skip it
			if ($existing_queue && strtotime('-15 minutes ago') <= strtotime($existing_queue['SpectrumQueue']['created'])) {
				return false;
			}
		}
		return true;
	}
}
